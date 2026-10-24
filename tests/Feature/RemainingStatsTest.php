<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\MaintenanceStatus;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Budget;
use App\Models\CommissionTeam;
use App\Models\Document;
use App\Models\InstrumentMaintenance;
use App\Models\InstrumentMovement;
use App\Models\LiturgicalSeason;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RemainingStatsTest extends TestCase
{
    use RefreshDatabase;

    public static function modules(): array
    {
        return [
            ['Members/Index', 2], ['Works/Index', 2], ['Budgets/Index', 2],
            ['Instruments/Movements', 1], ['Instruments/Maintenances', 1],
            ['Seasons/Index', 2], ['Users/Index', 1],
        ];
    }

    private function statsComponent(string $module): object
    {
        $class = 'App\\Livewire\\'.str_replace('/', '\\', $module);

        return new $class;
    }

    private function normalize(array $stats): array
    {
        if (isset($stats['par_voix'])) {
            $stats['par_voix'] = $stats['par_voix']->all();
            ksort($stats['par_voix']);
        }

        return $stats;
    }

    #[DataProvider('modules')]
    public function test_populated_statistics_preserve_values_and_sql_budget(string $module, int $budget): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $reference = 'reference'.str_replace('/', '', $module);
        $expected = $this->normalize($this->$reference());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $actual = $this->normalize($this->statsComponent($module)->stats());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($expected, $actual);
        $this->assertLessThanOrEqual($budget, $queries);
    }

    #[DataProvider('modules')]
    public function test_empty_statistics_preserve_types_and_zeros(string $module, int $budget): void
    {
        $reference = 'reference'.str_replace('/', '', $module);
        $this->assertSame($this->normalize($this->$reference()), $this->normalize($this->statsComponent($module)->stats()));
    }

    public function test_member_and_work_totals_exclude_deleted_rows(): void
    {
        $this->seed();
        $member = Member::firstOrFail();
        $work = Work::firstOrFail();
        $membersBefore = $this->statsComponent('Members/Index')->stats()['total'];
        $worksBefore = $this->statsComponent('Works/Index')->stats()['total'];
        $member->delete();
        $work->delete();
        $this->assertSame($membersBefore - 1, $this->statsComponent('Members/Index')->stats()['total']);
        $this->assertSame($worksBefore - 1, $this->statsComponent('Works/Index')->stats()['total']);
    }

    public function test_partition_count_follows_designation_and_publication_without_stale_cache(): void
    {
        $leader = User::factory()->role(UserRole::CommissionLiturgique)->create();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $admin = User::factory()->role(UserRole::Admin)->create();
        CommissionTeam::where('commission', Commission::Liturgique->value)->update(['leader_id' => $leader->id, 'associate_id' => $associate->id]);
        $document = Document::factory()->create(['commission' => Commission::Liturgique, 'category' => 'Partition', 'visibility' => 'private']);
        foreach ([[$leader, 1], [$associate, 1], [$admin, 0]] as [$user, $expected]) {
            $this->actingAs($user);
            $this->assertSame($expected, $this->statsComponent('Works/Index')->stats()['partitions']);
        }
        $document->update(['visibility' => 'published']);
        $this->assertSame(1, $this->statsComponent('Works/Index')->stats()['partitions']);
        $document->update(['visibility' => 'private']);
        $this->assertSame(0, $this->statsComponent('Works/Index')->stats()['partitions']);
    }

    public function test_dates_include_today_without_counting_missing_dates_as_overdue(): void
    {
        $this->seed();
        InstrumentMovement::query()->update(['actual_return_on' => null, 'expected_return_on' => null]);
        $ids = InstrumentMovement::limit(3)->pluck('id');
        $this->assertCount(3, $ids);
        InstrumentMovement::whereKey($ids[0])->update(['expected_return_on' => today()->subDay()]);
        InstrumentMovement::whereKey($ids[1])->update(['expected_return_on' => today()]);
        InstrumentMovement::whereKey($ids[2])->update(['expected_return_on' => today()->subDay(), 'actual_return_on' => today()]);
        $this->assertSame(1, $this->statsComponent('Instruments/Movements')->stats()['en_retard']);
        LiturgicalSeason::query()->update(['starts_on' => null, 'ends_on' => null]);
        $season = LiturgicalSeason::firstOrFail();
        $season->update(['starts_on' => today(), 'ends_on' => today()]);
        $this->assertSame(1, $this->statsComponent('Seasons/Index')->stats()['encours']);
        $season->update(['ends_on' => null]);
        $this->assertSame(1, $this->statsComponent('Seasons/Index')->stats()['encours']);
    }

    private function referenceMembersIndex(): array
    {
        return [
            'total' => Member::count(),
            'actif' => Member::where('status', MemberStatus::Actif->value)->count(),
            'invite' => Member::where('status', MemberStatus::Invite->value)->count(),
            'par_voix' => Member::where('status', MemberStatus::Actif->value)
                ->selectRaw('voice, count(*) as total')
                ->groupBy('voice')
                ->pluck('total', 'voice'),
        ];
    }

    private function referenceWorksIndex(): array
    {
        return [
            'total' => Work::count(),
            'repertoire' => Work::where('is_repertoire', true)->count(),
            'partitions' => Document::query()->readable(auth()->user())->where('commission', 'commission_liturgique')->where('category', 'Partition')->count(),
            'duree' => (int) Work::sum('duration_minutes'),
        ];
    }

    private function referenceBudgetsIndex(): array
    {
        $allocated = (float) Budget::sum('allocated_amount');
        $committed = (float) Budget::sum('committed_amount');
        $realized = (float) Budget::sum('opening_realized_amount') + (float) Transaction::validated()->where('type', 'depense')->whereHas('budget')->sum('amount');

        return [
            'total' => Budget::count(),
            'alloue' => $allocated,
            'engage' => $committed,
            'realise' => $realized,
            'disponible' => $allocated - $realized,
            'taux' => $allocated > 0 ? round($realized / $allocated * 100, 1) : 0.0,
        ];
    }

    private function referenceInstrumentsMovements(): array
    {
        $enCours = InstrumentMovement::whereNull('actual_return_on')->count();
        $enRetard = InstrumentMovement::whereNull('actual_return_on')
            ->whereNotNull('expected_return_on')
            ->whereDate('expected_return_on', '<', today())
            ->count();

        return [
            'total' => InstrumentMovement::count(),
            'en_cours' => $enCours,
            'en_retard' => $enRetard,
            'clotures' => InstrumentMovement::whereNotNull('actual_return_on')->count(),
        ];
    }

    private function referenceInstrumentsMaintenances(): array
    {
        return [
            'total' => InstrumentMaintenance::count(),
            'planifiees' => InstrumentMaintenance::whereIn('status', [
                MaintenanceStatus::Planifiee->value,
                MaintenanceStatus::EnCours->value,
            ])->count(),
            'terminees' => InstrumentMaintenance::where('status', MaintenanceStatus::Terminee->value)->count(),
            'cout' => (float) InstrumentMaintenance::sum('cost'),
        ];
    }

    private function referenceSeasonsIndex(): array
    {
        return [
            'total' => LiturgicalSeason::count(),
            'oeuvres' => Work::whereNotNull('season_id')->count(),
            'encours' => LiturgicalSeason::whereNotNull('starts_on')
                ->whereDate('starts_on', '<=', today())
                ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
                ->count(),
        ];
    }

    private function referenceUsersIndex(): array
    {
        $rolesCommission = array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => str_starts_with($role->value, 'commission_')
        ));

        return [
            'total' => User::count(),
            'admins' => User::where('role', UserRole::Admin->value)->count(),
            'commissions' => User::whereIn('role', array_map(
                fn (UserRole $role): string => $role->value,
                $rolesCommission
            ))->count(),
            'liens' => User::whereNotNull('member_id')->count(),
            'commissions_libelles' => collect($rolesCommission)
                ->map(fn (UserRole $role): string => strtolower($role->label()))
                ->implode(', '),
        ];
    }
}
