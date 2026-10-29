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

    public function test_apercu_et_telechargement_sont_deux_actions_distinctes_et_privees(): void
    {
        Storage::fake('local');
        $user = User::factory()->role(UserRole::Secretaire)->create();
        CommissionTeam::where('commission', Commission::Secretariat->value)->update(['leader_id' => $user->id]);
        $document = Document::factory()->create([
            'commission' => Commission::Secretariat,
            'path' => 'documents/notice.txt',
            'original_name' => 'notice.txt',
            'mime_type' => 'text/plain',
        ]);
        Storage::disk('local')->put($document->path, 'Contenu privé à visualiser');

        $preview = $this->actingAs($user)->get(route('documents.preview', $document));
        $preview->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertStreamedContent('Contenu privé à visualiser');
        $this->assertStringStartsWith('inline;', $preview->headers->get('Content-Disposition'));

        $download = $this->get(route('documents.download', $document));
        $download->assertOk()->assertStreamedContent('Contenu privé à visualiser');
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
    }

    public function test_apercu_refuse_les_formats_non_affichables_et_les_utilisateurs_non_autorises(): void
    {
        Storage::fake('local');
        $manager = User::factory()->role(UserRole::Admin)->create();
        $outsider = User::factory()->role(UserRole::Choriste)->create();
        $office = Document::factory()->create([
            'commission' => Commission::Direction,
            'visibility' => 'private',
            'path' => 'documents/rapport.docx',
            'original_name' => 'rapport.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
        Storage::disk('local')->put($office->path, 'fichier bureautique fictif');

        $this->actingAs($manager)->get(route('documents.preview', $office))->assertStatus(415);
        Livewire::actingAs($manager)->test(Index::class)->call('preview', $office->id)->assertStatus(415);
        $this->actingAs($outsider)->get(route('documents.preview', $office))->assertForbidden();
    }

    public function test_un_document_bureautique_prepare_est_affiche_comme_pdf_prive(): void
    {
        Storage::fake('local');
        $manager = User::factory()->role(UserRole::Admin)->create();
        $office = Document::factory()->create([
            'commission' => Commission::Direction,
            'visibility' => 'private',
            'path' => 'documents/rapport.docx',
            'original_name' => 'rapport.docx',
            'preview_path' => 'document-previews/rapport.pdf',
            'preview_status' => 'ready',
        ]);
        Storage::disk('local')->put($office->path, 'source Word');
        Storage::disk('local')->put($office->preview_path, '%PDF aperçu privé');

        $response = $this->actingAs($manager)->get(route('documents.preview', $office));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF aperçu privé', $response->getFile()->getContent());
    }

    public function test_la_fenetre_apercu_reverifie_les_droits_apres_une_restriction(): void
    {
        $reader = User::factory()->role(UserRole::Choriste)->create();
        $document = Document::factory()->create([
            'commission' => Commission::Direction,
            'visibility' => 'published',
            'original_name' => 'annonce.pdf',
            'mime_type' => 'application/pdf',
        ]);
        $component = Livewire::actingAs($reader)->test(Index::class)
            ->call('preview', $document->id)
            ->assertSet('previewDocumentId', $document->id);

        $document->update(['visibility' => 'private']);

        $component->call('$refresh')->assertForbidden();
        $this->actingAs($reader)->get(route('documents.preview', $document))->assertForbidden();
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
