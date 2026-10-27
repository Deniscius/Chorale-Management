<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Livewire\Documents\Form;
use App\Livewire\Documents\Index;
use App\Livewire\Documents\VisibilityForm;
use App\Models\CommissionTeam;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_and_direct_access_agree_for_every_commission_role_and_visibility(): void
    {
        $users = collect(UserRole::cases())->map(fn ($role) => User::factory()->role($role)->create());
        $associate = User::factory()->role(UserRole::Choriste)->create();
        CommissionTeam::where('commission', Commission::Liturgique->value)->update(['associate_id' => $associate->id]);
        $users->push($associate);
        foreach (Commission::cases() as $commission) {
            foreach (['private', 'commission', 'published', 'unknown'] as $visibility) {
                $doc = Document::factory()->create(compact('commission', 'visibility'));
                foreach ($users as $user) {
                    $expected = match ($visibility) {
                        'private' => $commission->canManageArchive($user),
                        'commission' => $commission->canView($user) || $commission->canManageArchive($user),
                        'published' => true,
                        default => false,
                    };
                    $this->assertSame($expected, $doc->canView($user));
                    $this->assertSame($expected, Document::readable($user)->whereKey($doc->id)->exists());
                }
                $this->assertFalse($doc->canView(null));
                $this->assertFalse(Document::readable(null)->whereKey($doc->id)->exists());
                $foreign = $users->first()->replicate();
                $foreign->tenant_id = $doc->tenant_id + 1000;
                $this->assertFalse($doc->canView($foreign));
                $this->assertFalse(Document::readable($foreign)->whereKey($doc->id)->exists());
            }
        }
    }

    public function test_import_defaults_to_managers_and_all_three_choices_are_kept(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin);
        foreach ([null, 'private', 'commission', 'published'] as $visibility) {
            $data = ['commission' => Commission::Instruments->value, 'title' => 'Notice'];
            if ($visibility !== null) {
                $data['visibility'] = $visibility;
            }
            $doc = app(DocumentArchive::class)->save(null, $data, UploadedFile::fake()->create('notice.txt', 1, 'text/plain'));
            $this->assertSame($visibility ?? 'private', $doc->visibility);
        }
    }

    public function test_whole_choir_sharing_is_discoverable_and_revocation_protects_bytes_and_metadata(): void
    {
        Storage::fake('local');
        $admin = User::factory()->role(UserRole::Admin)->create();
        $reader = User::factory()->role(UserRole::Choriste)->create();
        $doc = Document::factory()->create(['commission' => Commission::Direction, 'visibility' => 'private', 'title' => 'Annonce direction confidentielle', 'path' => 'documents/notice.txt', 'mime_type' => 'text/plain']);
        Storage::disk('local')->put($doc->path, 'Notice de test');
        $this->actingAs($reader)->get(route('documents.download', $doc))->assertForbidden();
        $this->get('/documents?commission='.Commission::Direction->value)->assertDontSee($doc->title);
        Livewire::actingAs($admin)->test(VisibilityForm::class, ['document' => $doc])->set('visibility', 'published')->call('save')->assertHasNoErrors();
        $this->actingAs($reader)->get('/documents?commission='.Commission::Direction->value)->assertOk()->assertSee($doc->title);
        $this->get(route('documents.download', $doc))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        Livewire::actingAs($reader)->test(VisibilityForm::class, ['document' => $doc->fresh()])->assertForbidden();
        Livewire::actingAs($reader)->test(Index::class)->call('changeVisibility', $doc->id)->assertForbidden();
        Livewire::actingAs($admin)->test(VisibilityForm::class, ['document' => $doc->fresh()])->set('visibility', 'private')->call('save')->assertHasNoErrors();
        $this->actingAs($reader)->get(route('documents.download', $doc, ['inline' => 1]))->assertForbidden();
        $this->get('/documents?commission='.Commission::Direction->value)->assertDontSee($doc->title);
        $this->assertSame('Notice de test', Storage::disk('local')->get($doc->fresh()->path));
        auth()->logout();
        $this->get(route('documents.download', $doc))->assertRedirect(route('login'));
    }

    public function test_stale_visibility_and_metadata_forms_cannot_restore_old_access(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $doc = Document::factory()->create(['commission' => Commission::Instruments, 'visibility' => 'published', 'title' => 'Version initiale']);
        $visibility = Livewire::actingAs($admin)->test(VisibilityForm::class, ['document' => $doc]);
        $metadata = Livewire::actingAs($admin)->test(Form::class, ['document' => $doc]);
        app(DocumentArchive::class)->setVisibility($doc->id, 'private', 'published');
        $visibility->set('visibility', 'commission')->call('save')->assertHasErrors(['visibility']);
        $metadata->set('title', 'Ancienne page')->call('save')->assertHasErrors(['visibility']);
        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'visibility' => 'private', 'title' => 'Version initiale']);
    }

    public function test_permission_is_rechecked_when_an_already_open_form_is_submitted(): void
    {
        $manager = User::factory()->role(UserRole::Choriste)->create();
        CommissionTeam::where('commission', Commission::Liturgique->value)->update(['associate_id' => $manager->id]);
        $doc = Document::factory()->create(['commission' => Commission::Liturgique, 'visibility' => 'private']);
        $form = Livewire::actingAs($manager)->test(VisibilityForm::class, ['document' => $doc])->set('visibility', 'published');
        CommissionTeam::where('commission', Commission::Liturgique->value)->update(['associate_id' => null]);
        $form->call('save')->assertForbidden();
        $this->assertSame('private', $doc->fresh()->visibility);
    }

    public function test_unknown_visibility_is_rejected_without_changing_the_document(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $doc = Document::factory()->create(['commission' => Commission::Instruments, 'visibility' => 'private']);
        Livewire::actingAs($admin)->test(VisibilityForm::class, ['document' => $doc])->set('visibility', 'public')->call('save')->assertHasErrors(['visibility']);
        $this->assertSame('private', $doc->fresh()->visibility);
    }
}
