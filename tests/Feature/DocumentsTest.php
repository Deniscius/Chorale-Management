<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Livewire\Documents\Form;
use App\Livewire\Documents\Index;
use App\Models\CommissionTeam;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Archives documentaires : consultation filtrée, dépôt et téléchargement.
 */
class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_choriste_consulte_les_archives_sans_pouvoir_deposer(): void
    {
        $user = User::factory()->role(UserRole::Choriste)->create();

        $this->actingAs($user)
            ->get('/documents')
            ->assertOk();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->assertForbidden();
    }

    public function test_un_secretaire_archive_un_document_dans_sa_commission(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Secretaire)->create();
        CommissionTeam::where('commission', Commission::Secretariat->value)->update(['leader_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(Form::class, ['document' => null, 'commission' => Commission::Secretariat->value])
            ->set('title', 'Compte rendu de séance')
            ->set('category', 'Compte rendu')
            ->set('file', UploadedFile::fake()->create('compte-rendu.pdf', 120, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $document = Document::firstWhere('title', 'Compte rendu de séance');

        $this->assertNotNull($document);
        $this->assertSame(Commission::Secretariat, $document->commission);
        $this->assertSame($user->id, $document->uploaded_by);
        $this->assertSame('compte-rendu.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_le_depot_dans_une_commission_non_autorisee_est_refuse(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Secretaire)->create();
        CommissionTeam::where('commission', Commission::Secretariat->value)->update(['leader_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(Form::class, ['document' => null, 'commission' => Commission::Secretariat->value])
            ->set('commission', Commission::Tresorerie->value)
            ->set('title', 'Tentative')
            ->set('file', UploadedFile::fake()->create('tentative.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertForbidden();
    }

    public function test_un_document_peut_etre_remplace(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Secretaire)->create();
        CommissionTeam::where('commission', Commission::Secretariat->value)->update(['leader_id' => $user->id]);

        $component = Livewire::actingAs($user)
            ->test(Form::class, ['document' => null, 'commission' => Commission::Secretariat->value])
            ->set('title', 'Rapport annuel')
            ->set('file', UploadedFile::fake()->create('rapport.pdf', 50, 'application/pdf'))
            ->call('save');

        $document = Document::firstWhere('title', 'Rapport annuel');
        $firstPath = $document->path;

        $component
            ->set('title', 'Rapport annuel corrigé')
            ->set('file', UploadedFile::fake()->create('rapport-v2.pdf', 60, 'application/pdf'))
            ->call('save');

        $document->refresh();

        $this->assertSame('Rapport annuel corrigé', $document->title);
        $this->assertNotSame($firstPath, $document->path);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_un_role_autorise_consulte_le_document_en_ligne(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Secretaire)->create();
        CommissionTeam::where('commission', Commission::Secretariat->value)->update(['leader_id' => $user->id]);
        $path = 'documents/'.Commission::Secretariat->value.'/pv.pdf';

        Storage::disk('local')->put($path, '%PDF-1.4 contenu de test');

        $document = Document::factory()->create([
            'commission' => Commission::Secretariat->value,
            'path' => $path,
            'original_name' => 'pv.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($user)->get(route('documents.download', $document));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_un_role_sans_droit_ne_peut_pas_telecharger_le_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::CommissionInstruments)->create();
        $path = 'documents/'.Commission::Secretariat->value.'/secret.pdf';

        Storage::disk('local')->put($path, 'contenu');

        $document = Document::factory()->create([
            'commission' => Commission::Secretariat->value,
            'path' => $path,
        ]);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_un_fichier_manquant_renvoie_une_erreur_404(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Secretaire)->create();
        CommissionTeam::where('commission', Commission::Secretariat->value)->update(['leader_id' => $user->id]);

        $document = Document::factory()->create([
            'commission' => Commission::Secretariat->value,
            'path' => 'documents/introuvable.pdf',
        ]);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertNotFound();
    }

    public function test_la_suppression_retire_le_fichier_du_disque(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::Secretaire)->create();
        CommissionTeam::where('commission', Commission::Secretariat->value)->update(['leader_id' => $user->id]);
        $path = 'documents/'.Commission::Secretariat->value.'/a-supprimer.pdf';

        Storage::disk('local')->put($path, 'contenu');

        $document = Document::factory()->create([
            'commission' => Commission::Secretariat->value,
            'path' => $path,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('delete', $document->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
