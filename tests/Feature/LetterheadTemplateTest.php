<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\ProcessLetterheadTemplate;
use App\Livewire\Settings\Letterheads;
use App\Models\LetterheadTemplate;
use App\Models\User;
use App\Services\DocxSecurityInspector;
use App\Services\LetterheadManager;
use App\Services\LetterheadProcessor;
use App\Services\PdfSecurityInspector;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class LetterheadTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_an_administrator_imports_a_safe_docx_for_background_processing(): void
    {
        Storage::fake('local');
        Bus::fake();
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post(route('settings.letterheads.upload'), [
            'document' => $this->docx(),
            'header_height_mm' => 52,
        ])->assertRedirect(route('settings.chorale').'#papier-entete');

        $template = LetterheadTemplate::sole();
        $this->assertSame(52, $template->header_height_mm);
        $this->assertSame(LetterheadTemplate::STATUS_PENDING, $template->status);
        Storage::disk('local')->assertExists($template->original_path);
        Bus::assertDispatchedAfterResponse(ProcessLetterheadTemplate::class, fn ($job) => $job->templateId === $template->id && $job->tenantId === 1);

        $this->actingAs($admin)->get(route('settings.chorale'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('action="'.route('settings.letterheads.upload').'"', false);
    }

    public function test_external_resources_are_rejected_before_any_storage(): void
    {
        Storage::fake('local');
        $file = $this->docx('<Relationship Id="rId1" Target="https://example.test/pixel.png" TargetMode="External"/>');

        try {
            app(DocxSecurityInspector::class)->inspect($file);
            $this->fail('Un DOCX contenant une ressource externe a été accepté.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }
        $this->assertDatabaseCount('letterhead_templates', 0);
    }

    public function test_a_safe_text_hyperlink_in_the_letterhead_is_allowed(): void
    {
        $file = $this->docx('<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://chorale.example" TargetMode="External"/>');

        app(DocxSecurityInspector::class)->inspect($file);

        $this->addToAssertionCount(1);
    }

    public function test_an_administrator_can_submit_a_static_pdf_letterhead(): void
    {
        Storage::fake('local');
        Bus::fake();
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post(route('settings.letterheads.upload'), [
            'document' => $this->pdf(),
            'header_height_mm' => 45,
        ])->assertRedirect(route('settings.chorale').'#papier-entete');

        $template = LetterheadTemplate::sole();
        $this->assertSame('papier-entete.pdf', $template->original_name);
        Bus::assertDispatchedAfterResponse(ProcessLetterheadTemplate::class);
    }

    public function test_an_interactive_pdf_is_rejected(): void
    {
        $file = $this->pdf('/OpenAction << /S /JavaScript >>');

        $this->expectException(ValidationException::class);

        app(PdfSecurityInspector::class)->inspect($file);
    }

    public function test_activation_is_atomic_and_keeps_the_previous_version(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(UserRole::Admin)->create();
        $files = app(PrivateFiles::class);
        $oldPath = app(TenantContext::class)->path('letterheads/1/header.png');
        $newPath = app(TenantContext::class)->path('letterheads/2/header.png');
        $files->storeContents($oldPath, base64_decode(self::PNG), 'image/png');
        $files->storeContents($newPath, base64_decode(self::PNG), 'image/png');
        $old = LetterheadTemplate::create(['original_name' => 'ancien.docx', 'status' => 'active', 'header_path' => $oldPath, 'uploaded_by' => $admin->id, 'activated_at' => now()->subDay()]);
        $new = LetterheadTemplate::create(['original_name' => 'nouveau.docx', 'status' => 'ready', 'header_path' => $newPath, 'uploaded_by' => $admin->id]);

        app(LetterheadManager::class)->activate($new);

        $this->assertSame(LetterheadTemplate::STATUS_REPLACED, $old->fresh()->status);
        $this->assertSame(LetterheadTemplate::STATUS_ACTIVE, $new->fresh()->status);
        $this->assertTrue($files->exists($oldPath));
        $this->actingAs($admin)->get(route('settings.letterheads.sample'))->assertOk()->assertSee('data:image/png;base64,', false);
    }

    public function test_a_non_administrator_cannot_manage_or_read_letterhead_files(): void
    {
        Storage::fake('local');
        $member = User::factory()->role(UserRole::Choriste)->create();
        $path = app(TenantContext::class)->path('letterheads/1/original.docx');
        app(PrivateFiles::class)->storeContents($path, 'private', 'application/octet-stream');
        $template = LetterheadTemplate::create(['original_name' => 'secret.docx', 'original_path' => $path]);

        Livewire::actingAs($member)->test(Letterheads::class)->assertForbidden();
        $this->actingAs($member)->post(route('settings.letterheads.upload'), [
            'document' => $this->docx(), 'header_height_mm' => 45,
        ])->assertForbidden();
        $this->actingAs($member)->get(route('settings.letterheads.file', [$template, 'original']))->assertForbidden();
    }

    public function test_a_real_word_document_is_converted_to_private_previews(): void
    {
        if (! is_executable('/usr/bin/soffice') || ! is_executable('/usr/bin/pdftoppm')) {
            $this->markTestSkipped('LibreOffice et Poppler sont validés dans l’image de production.');
        }
        Storage::fake('local');
        Bus::fake();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $template = app(LetterheadManager::class)->upload($this->docx(), 45, $admin);

        app(LetterheadProcessor::class)->process($template);

        $template->refresh();
        $this->assertSame(LetterheadTemplate::STATUS_READY, $template->status, $template->error_message ?? '');
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($template->preview_path));
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($template->header_path));
    }

    public function test_recalculating_an_active_template_keeps_it_active_until_the_new_version_is_approved(): void
    {
        Bus::fake();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $active = LetterheadTemplate::create([
            'original_name' => 'officiel.docx', 'original_path' => 'letterheads/original.docx',
            'header_path' => 'letterheads/header.png', 'status' => LetterheadTemplate::STATUS_ACTIVE,
            'header_height_mm' => 45, 'uploaded_by' => $admin->id, 'activated_at' => now(),
        ]);
        $this->actingAs($admin);

        app(LetterheadManager::class)->reprocess($active, 60);

        $this->assertSame(LetterheadTemplate::STATUS_ACTIVE, $active->fresh()->status);
        $replacement = LetterheadTemplate::query()->whereKeyNot($active->id)->sole();
        $this->assertSame(LetterheadTemplate::STATUS_PENDING, $replacement->status);
        $this->assertSame(60, $replacement->header_height_mm);
        Bus::assertDispatchedAfterResponse(ProcessLetterheadTemplate::class, fn ($job) => $job->templateId === $replacement->id);
    }

    public function test_an_administrator_can_delete_the_active_template_and_its_private_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(UserRole::Admin)->create();
        $base = app(TenantContext::class)->path('letterheads/42');
        $files = app(PrivateFiles::class);
        foreach (['original.docx', 'preview.png', 'header.png'] as $name) {
            $files->storeContents($base.'/'.$name, $name, 'application/octet-stream');
        }
        $template = LetterheadTemplate::create([
            'original_name' => 'ancien.docx',
            'original_path' => $base.'/original.docx',
            'preview_path' => $base.'/preview.png',
            'header_path' => $base.'/header.png',
            'status' => LetterheadTemplate::STATUS_ACTIVE,
            'activated_at' => now(),
            'uploaded_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)->test(Letterheads::class)
            ->assertSee('Supprimer')
            ->call('delete', $template->id)
            ->assertDispatched('toast', type: 'success', message: 'Le fichier et ses aperçus ont été supprimés.');

        $this->assertDatabaseMissing('letterhead_templates', ['id' => $template->id]);
        foreach (['original.docx', 'preview.png', 'header.png'] as $name) {
            Storage::disk('local')->assertMissing($base.'/'.$name);
        }
    }

    public function test_deleting_a_recalculated_version_preserves_the_shared_active_original(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(UserRole::Admin)->create();
        $files = app(PrivateFiles::class);
        $shared = app(TenantContext::class)->path('letterheads/1/original.docx');
        $replacementPreview = app(TenantContext::class)->path('letterheads/2/preview.png');
        $files->storeContents($shared, 'original', 'application/octet-stream');
        $files->storeContents($replacementPreview, 'preview', 'image/png');
        LetterheadTemplate::create([
            'original_name' => 'officiel.docx', 'original_path' => $shared,
            'status' => LetterheadTemplate::STATUS_ACTIVE, 'uploaded_by' => $admin->id,
        ]);
        $replacement = LetterheadTemplate::create([
            'original_name' => 'officiel.docx', 'original_path' => $shared,
            'preview_path' => $replacementPreview, 'status' => LetterheadTemplate::STATUS_READY,
            'uploaded_by' => $admin->id,
        ]);
        $this->actingAs($admin);

        app(LetterheadManager::class)->delete($replacement);

        $this->assertTrue($files->exists($shared));
        $this->assertFalse($files->exists($replacementPreview));
        $this->assertDatabaseCount('letterhead_templates', 1);
    }

    public function test_a_queued_conversion_quietly_stops_if_the_template_was_deleted(): void
    {
        (new ProcessLetterheadTemplate(1, 999999))->handle(
            app(TenantContext::class),
            app(LetterheadProcessor::class),
        );

        $this->addToAssertionCount(1);
    }

    private function docx(string $relationship = ''): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'letterhead-docx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Papier officiel</w:t></w:r></w:p><w:sectPr/></w:body></w:document>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationship.'</Relationships>');
        $zip->close();
        $contents = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('papier-entete.docx', $contents);
    }

    private function pdf(string $extra = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'papier-entete.pdf',
            "%PDF-1.4\n1 0 obj << /Type /Catalog {$extra} >> endobj\n%%EOF",
        );
    }
}
