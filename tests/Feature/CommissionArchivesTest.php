<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Livewire\Documents\Form;
use App\Livewire\Documents\Index;
use App\Livewire\Documents\TeamSettings;
use App\Models\AuditEvent;
use App\Models\CommissionTeam;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\LiturgicalSeason;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CommissionTeamWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CommissionArchivesTest extends TestCase
{
    use RefreshDatabase;

    private function team(Commission $commission = Commission::Liturgique): array
    {
        $leader = User::factory()->role($commission === Commission::Liturgique ? UserRole::CommissionLiturgique : UserRole::Secretaire)->create();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        CommissionTeam::where('commission', $commission->value)->update(['leader_id' => $leader->id, 'associate_id' => $associate->id]);

        return [$leader, $associate];
    }

    private function document(Commission $commission = Commission::Liturgique, array $data = []): Document
    {
        Storage::fake('local');
        $d = Document::factory()->create(array_merge(['commission' => $commission, 'title' => 'Partition privée confidentielle', 'category' => 'Partition', 'visibility' => 'private', 'path' => 'documents/synthetic.pdf', 'mime_type' => 'application/pdf', 'original_name' => 'synthetic.pdf'], $data));
        Storage::disk('local')->put($d->path, '%PDF synthetic');

        return $d;
    }

    public function test_private_partition_is_reserved_to_the_two_designated_users(): void
    {
        [$leader,$associate] = $this->team();
        $doc = $this->document();
        foreach (UserRole::cases() as $role) {
            $u = User::factory()->role($role)->create();
            $this->actingAs($u)->get(route('documents.download', $doc))->assertForbidden();
            $this->get('/documents?commission=commission_liturgique')->assertOk()->assertDontSee($doc->title);
        }
        foreach ([$leader, $associate] as $u) {
            $this->actingAs($u)->get(route('documents.download', $doc))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->get('/documents?commission=commission_liturgique')->assertSee($doc->title);
        }
    }

    public function test_publication_is_internal_and_reversible(): void
    {
        [$leader,$associate] = $this->team();
        $doc = $this->document();
        $outsider = User::factory()->role(UserRole::Choriste)->create();
        Livewire::actingAs($associate)->test(Index::class, ['commission' => Commission::Liturgique->value])->call('share', $doc->id, true)->assertHasNoErrors();
        $this->actingAs($outsider)->get(route('documents.download', $doc))->assertOk();
        Livewire::actingAs($leader)->test(Index::class)->call('share', $doc->id, false)->assertHasNoErrors();
        $this->actingAs($outsider)->get(route('documents.download', $doc))->assertForbidden();
        auth()->logout();
        $this->get(route('documents.download', $doc))->assertRedirect(route('login'));
    }

    public function test_ordinary_role_member_cannot_publish_edit_or_delete(): void
    {
        $this->team();
        $doc = $this->document();
        $other = User::factory()->role(UserRole::CommissionLiturgique)->create();
        foreach (['share', 'delete', 'edit'] as $action) {
            $test = Livewire::actingAs($other)->test(Index::class);
            $action === 'share' ? $test->call($action, $doc->id, true)->assertForbidden() : $test->call($action, $doc->id)->assertForbidden();
        }
        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'deleted_at' => null, 'visibility' => 'private']);
    }

    public function test_associate_replacement_revokes_an_already_open_page(): void
    {
        [$leader,$associate] = $this->team();
        $doc = $this->document();
        $page = Livewire::actingAs($associate)->test(Index::class)->set('commission', Commission::Liturgique->value)->assertSee($doc->title);
        $this->actingAs($leader);
        app(CommissionTeamWriter::class)->save(Commission::Liturgique, $leader->id, null);
        $this->actingAs($associate);
        $page->call('resetFilters')->assertDontSee($doc->title);
        $this->get(route('documents.download', $doc))->assertForbidden();
    }

    public function test_only_admin_assigns_leader_and_leader_assigns_associate(): void
    {
        [$leader,$associate] = $this->team();
        $replacement = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($leader);
        app(CommissionTeamWriter::class)->save(Commission::Liturgique, $leader->id, $replacement->id);
        $this->assertDatabaseHas('commission_teams', ['leader_id' => $leader->id, 'associate_id' => $replacement->id]);
        $event = AuditEvent::where('subject_type', CommissionTeam::class)->where('action', 'updated')->firstOrFail();
        $this->assertEquals($leader->id, $event->actor_id);
        $this->actingAs($associate);
        $this->expectException(HttpException::class);
        app(CommissionTeamWriter::class)->save(Commission::Liturgique, $leader->id, $associate->id);
    }

    public function test_admin_can_choose_any_account_but_not_a_missing_or_duplicate_person(): void
    {
        [$leader] = $this->team();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin);
        foreach ([[PHP_INT_MAX, null], [$leader->id, $leader->id]] as [$l,$a]) {
            try {
                app(CommissionTeamWriter::class)->save(Commission::Liturgique, $l, $a);
                $this->fail('Invalid assignment');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        Livewire::actingAs($admin)->test(TeamSettings::class, ['commission' => Commission::Liturgique->value])->set('leader_id', (string) $leader->id)->set('associate_id', null)->call('save')->assertHasNoErrors();
    }

    public function test_import_program_is_private_and_can_be_found_by_date_and_category(): void
    {
        Storage::fake('local');
        [$leader] = $this->team();
        Livewire::actingAs($leader)->test(Form::class, ['commission' => Commission::Liturgique->value])->set('title', 'Programme Messe de Noël')->set('category', 'Programme de messe')->set('document_date', '2026-12-25')->set('file', UploadedFile::fake()->create('programme.pdf', 20, 'application/pdf'))->call('save')->assertHasNoErrors();
        $doc = Document::firstOrFail();
        $this->assertSame('private', $doc->visibility);
        Storage::disk('local')->assertExists($doc->path);
        Livewire::actingAs($leader)->test(Index::class)->set('commission', Commission::Liturgique->value)->set('category', 'Programme de messe')->set('year', '2026')->assertSee('Programme Messe de Noël')->set('year', '2025')->assertDontSee('Programme Messe de Noël');
    }

    public function test_private_metadata_and_counts_are_not_exposed(): void
    {
        $this->team();
        $doc = $this->document();
        $other = User::factory()->role(UserRole::Choriste)->create();
        $test = Livewire::actingAs($other)->test(Index::class)->set('commission', Commission::Liturgique->value);
        $this->assertSame(0, $test->instance()->stats['total']);
        $this->assertSame([], $test->instance()->categories);
    }

    public function test_upload_validation_rejects_oversized_or_forbidden_files(): void
    {
        Storage::fake('local');
        [$leader] = $this->team();
        foreach ([UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'), UploadedFile::fake()->create('script.html', 1, 'text/html')] as $file) {
            Livewire::actingAs($leader)->test(Form::class, ['commission' => Commission::Liturgique->value])->set('title', 'Forbidden')->set('file', $file)->call('save')->assertHasErrors('file');
        }$this->assertDatabaseCount('documents', 0);
    }

    public function test_correspondence_attachment_uses_the_same_private_archive(): void
    {
        Storage::fake('local');
        [$leader,$associate] = $this->team(Commission::Secretariat);
        Livewire::actingAs($leader)->test(\App\Livewire\Correspondences\Form::class)->set('sender_name', 'Expéditeur test')->set('subject', 'Courrier de test')->set('attachment', UploadedFile::fake()->create('courrier.pdf', 10, 'application/pdf'))->call('save')->assertHasNoErrors();
        $doc = Document::firstOrFail();
        $this->assertNotNull($doc->correspondence_id);
        $this->assertSame('private', $doc->visibility);
        $this->actingAs($associate)->get(route('documents.download', $doc))->assertOk();
        $this->get(route('correspondences.documents', $doc->correspondence_id))->assertOk()->assertSee($doc->title);
        $this->actingAs(User::factory()->role(UserRole::Tresorier)->create())->get(route('documents.download', $doc))->assertForbidden();
    }

    public function test_shared_courrier_attachment_does_not_grant_access_to_register(): void
    {
        [$leader] = $this->team(Commission::Secretariat);
        $c = Correspondence::factory()->create();
        $doc = $this->document(Commission::Secretariat, ['correspondence_id' => $c->id, 'visibility' => 'published']);
        $choriste = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($choriste)->get('/documents?commission=commission_secretariat')->assertSee($doc->title);
        $this->get(route('documents.download', $doc))->assertOk();
        $this->get('/courriers')->assertForbidden();
        $this->get(route('correspondences.documents', $c))->assertForbidden();
    }

    public function test_file_failure_rolls_back_the_correspondence(): void
    {
        Storage::fake('local');
        [$leader] = $this->team(Commission::Secretariat);
        $this->actingAs($leader);
        $dispatcher = clone Document::getEventDispatcher();
        Document::creating(fn () => throw new \RuntimeException('Synthetic DB failure'));
        try {
            Livewire::test(\App\Livewire\Correspondences\Form::class)->set('sender_name', 'Test')->set('subject', 'Rollback')->set('attachment', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->call('save');
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic DB failure', $e->getMessage());
        } finally {
            Document::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('correspondences', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
    }

    public function test_leader_role_change_revokes_private_read_and_write(): void
    {
        [$leader] = $this->team();
        $doc = $this->document();
        $form = Livewire::actingAs($leader)->test(Form::class, ['document' => $doc]);
        $leader->update(['role' => UserRole::Choriste]);
        $this->actingAs($leader->fresh())->get(route('documents.download', $doc))->assertForbidden();
        $form->call('save')->assertForbidden();
    }

    public function test_associate_can_import_but_cannot_assign_another_associate(): void
    {
        Storage::fake('local');
        [$leader, $associate] = $this->team();
        Livewire::actingAs($associate)->test(Form::class, ['commission' => Commission::Liturgique->value])
            ->set('title', 'Partition déposée par associé')->set('category', 'Partition')
            ->set('file', UploadedFile::fake()->create('partition.pdf', 10, 'application/pdf'))->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('documents', ['uploaded_by' => $associate->id, 'visibility' => 'private']);
        Livewire::actingAs($associate)->test(TeamSettings::class, ['commission' => Commission::Liturgique->value])->assertForbidden();
        Livewire::actingAs($leader)->test(Index::class)->set('commission', Commission::Liturgique->value)
            ->call('configureTeam')->assertSet('showTeam', true)->call('closeForm')->assertSet('showTeam', false);
    }

    public function test_card_filters_show_the_records_that_make_up_the_indicator(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Transaction::factory()->recette()->create(['description' => 'Recette validée visible']);
        Transaction::factory()->recette()->create(['description' => 'Recette brouillon exclue', 'status' => 'brouillon']);
        Transaction::factory()->depense()->create(['description' => 'Dépense exclue']);
        $this->actingAs($admin)->get('/transactions?type=recette&vue=validees')->assertOk()->assertSee('Recette validée visible')->assertDontSee('Recette brouillon exclue')->assertDontSee('Dépense exclue');
        LiturgicalSeason::factory()->create(['name' => 'Saison actuelle visible', 'slug' => 'actuelle-test', 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay()]);
        LiturgicalSeason::factory()->create(['name' => 'Saison passée exclue', 'slug' => 'passee-test', 'starts_on' => today()->subMonth(), 'ends_on' => today()->subDay()]);
        $this->get('/temps-liturgiques?vue=encours')->assertOk()->assertSee('Saison actuelle visible')->assertDontSee('Saison passée exclue');
    }

    public function test_aggregate_cards_have_real_links_and_filters(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        foreach (['/membres', '/courriers', '/transactions', '/cotisations', '/oeuvres', '/repetitions', '/celebrations', '/temps-liturgiques', '/instruments', '/instruments/mouvements', '/instruments/maintenance', '/instruments/ensembles', '/activites', '/budgets', '/dons', '/utilisateurs', '/rappels', '/documents'] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('#resultats', $html);
            $this->assertStringContainsString('id="resultats"', $html);
        }
        Correspondence::factory()->create(['subject' => 'À traiter exemple', 'status' => 'recu']);
        Correspondence::factory()->create(['subject' => 'Clos exemple', 'status' => 'clos']);
        $this->get('/courriers?vue=ouverts')->assertSee('À traiter exemple')->assertDontSee('Clos exemple');
    }
}
