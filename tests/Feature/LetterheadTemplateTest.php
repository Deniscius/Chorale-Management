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
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake();
        $admin = User::factory()->role(UserRole::Admin)->create();

        Livewire::actingAs($admin)->test(Letterheads::class)
            ->set('document', $this->docx())
            ->set('header_height_mm', 52)
            ->call('upload')
            ->assertHasNoErrors();

        $template = LetterheadTemplate::sole();
        $this->assertSame(52, $template->header_height_mm);
        $this->assertSame(LetterheadTemplate::STATUS_PENDING, $template->status);
        Storage::disk('local')->assertExists($template->original_path);
        Queue::assertPushed(ProcessLetterheadTemplate::class, fn ($job) => $job->templateId === $template->id && $job->tenantId === 1);
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
        $this->actingAs($member)->get(route('settings.letterheads.file', [$template, 'original']))->assertForbidden();
    }

    public function test_a_real_word_document_is_converted_to_private_previews(): void
    {
        if (! is_executable('/usr/bin/soffice') || ! is_executable('/usr/bin/pdftoppm')) {
            $this->markTestSkipped('LibreOffice et Poppler sont validés dans l’image de production.');
        }
        Storage::fake('local');
        Queue::fake();
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
        Queue::fake();
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
        Queue::assertPushed(ProcessLetterheadTemplate::class, fn ($job) => $job->templateId === $replacement->id);
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
}
