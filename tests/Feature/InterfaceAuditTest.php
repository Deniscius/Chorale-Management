<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Participants;
use App\Livewire\Members\Index;
use App\Models\Activity;
use App\Models\Correspondence;
use App\Models\Member;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InterfaceAuditTest extends TestCase
{
    use RefreshDatabase;

    public static function forms(): array
    {
        return array_map(fn ($c) => ['App\\Livewire\\'.$c], ['Activities\\Form', 'Budgets\\Form', 'Contributions\\Form', 'Correspondences\\Form', 'Donations\\Form', 'Instruments\\EnsembleForm', 'Instruments\\Form', 'Instruments\\MaintenanceForm', 'Instruments\\MovementForm', 'Members\\Form', 'Performances\\Form', 'Rehearsals\\Form', 'Seasons\\Form', 'Transactions\\Form', 'Users\\Form', 'Works\\Form']);
    }

    #[DataProvider('forms')]
    public function test_creation_forms_mount_without_a_model(string $class): void
    {
        Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())->test($class)->assertOk()->assertSee('Enregistrer');
    }

    public static function lists(): array
    {
        return array_map(fn ($c) => ['App\\Livewire\\'.$c], ['Activities\\Index', 'Budgets\\Index', 'Contributions\\Index', 'Correspondences\\Index', 'Donations\\Index', 'Instruments\\Ensembles', 'Instruments\\Index', 'Instruments\\Maintenances', 'Instruments\\Movements', 'Members\\Index', 'Performances\\Index', 'Rehearsals\\Index', 'Seasons\\Index', 'Transactions\\Index', 'Users\\Index', 'Works\\Index']);
    }

    #[DataProvider('lists')]
    public function test_open_modal_has_unique_field_ids(string $class): void
    {
        $component = Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())->test($class)->call('create')->assertSet('showForm', true);
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($component->html(), 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            $this->assertArrayNotHasKey($id, $ids, 'Duplicate field id '.$id.' in '.$class);
            $ids[$id] = true;
        }
    }

    public function test_access_and_participant_modals_dispatch_their_own_close_event(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();
        $activity = Activity::factory()->create();
        Livewire::actingAs($admin)->test(Index::class)->call('manageAccess', $member->id)->assertSee('close-access', false)->dispatch('close-access')->assertSet('showAccess', false);
        Livewire::test(\App\Livewire\Activities\Index::class)->call('manageParticipants', $activity->id)->assertSee('close-participants', false)->dispatch('close-participants')->assertSet('showParticipants', false);
    }

    public function test_livewire_pagination_renders_in_french_and_preserves_search(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Member::factory()->count(17)->create(['last_name' => 'AuditPagination']);
        Livewire::actingAs($admin)->test(Index::class)->set('search', 'AuditPagination')->assertSee('Affichage de')->assertDontSee('Showing')->call('gotoPage', 2, 'page')->assertSee('Affichage de 16 à 17 sur 17 résultats');
    }

    public function test_deleted_member_cannot_be_added_to_an_activity(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->create();
        $activity = Activity::factory()->create();
        $member->delete();
        Livewire::actingAs($admin)->test(Participants::class, ['activity' => $activity])->set('memberId', $member->id)->call('add')->assertHasErrors('memberId');
        $this->assertDatabaseCount('activity_participants', 0);
    }

    public function test_stale_edit_does_not_open_a_creation_form(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $work = Work::factory()->create();
        $work->delete();
        Livewire::actingAs($admin)->test(\App\Livewire\Works\Index::class)->call('edit', $work->id)->assertNotFound();
    }

    public static function programs(): array
    {
        return [['Rehearsals', 'rehearsals', 'rehearsal_work'], ['Performances', 'performances', 'performance_work']];
    }

    #[DataProvider('programs')]
    public function test_program_rejects_missing_or_deleted_works(string $module, string $table, string $pivot): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $work = Work::factory()->create();
        $work->delete();
        foreach ([999999, $work->id, 'invalid'] as $id) {
            $form = Livewire::actingAs($admin)->test('App\\Livewire\\'.$module.'\\Form')->set('works', [$id]);
            if ($module === 'Performances') {
                $form->set('title', 'Audit');
            }$form->call('save')->assertHasErrors('works.0');
        }$this->assertDatabaseCount($table, 0);
    }

    #[DataProvider('programs')]
    public function test_program_failure_rolls_back_the_event(string $module, string $table, string $pivot): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $work = Work::factory()->create();
        $connection = DB::connection();
        $original = $connection->getEventDispatcher();
        $connection->setEventDispatcher(clone $original);
        $connection->listen(function ($query) use ($pivot) {
            if (str_contains(strtolower($query->sql), 'insert into "'.$pivot.'"')) {
                throw new \RuntimeException('Synthetic pivot failure');
            }
        });
        try {
            $form = Livewire::actingAs($admin)->test('App\\Livewire\\'.$module.'\\Form')->set('works', [$work->id]);
            if ($module === 'Performances') {
                $form->set('title', 'Audit rollback');
            }$form->call('save');
            $this->fail('Failure expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic pivot failure', $e->getMessage());
        } finally {
            $connection->setEventDispatcher($original);
        }
        $this->assertDatabaseCount($table, 0);
        $this->assertDatabaseCount($pivot, 0);
    }

    public function test_malformed_courrier_year_does_not_cause_a_database_error(): void
    {
        Correspondence::factory()->create(['subject' => 'Courrier exclu du filtre']);
        $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get('/courriers?annee=invalid')->assertOk()->assertDontSee('Courrier exclu du filtre');
    }
}
