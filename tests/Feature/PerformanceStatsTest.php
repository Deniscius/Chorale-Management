<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ContributionStatus;
use App\Enums\CorrespondenceStatus;
use App\Enums\CorrespondenceType;
use App\Enums\EventStatus;
use App\Enums\InstrumentCondition;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Livewire\Settings\Chorale;
use App\Models\Activity;
use App\Models\Contribution;
use App\Models\Correspondence;
use App\Models\Donation;
use App\Models\Instrument;
use App\Models\Performance;
use App\Models\Rehearsal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PerformanceStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branding_updates_are_visible_after_the_first_render(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        Livewire::test(Chorale::class)->set('name', 'Nom après optimisation')->call('save')->assertHasNoErrors();
        $this->get('/dashboard')->assertOk()->assertSee('Nom après optimisation');
    }

    public static function modules(): array
    {
        return [['Performances', 1], ['Activities', 1], ['Correspondences', 1], ['Rehearsals', 2], ['Instruments', 1], ['Transactions', 1], ['Contributions', 1], ['Donations', 2]];
    }

    #[DataProvider('modules')]
    public function test_statistics_keep_the_same_values_with_fewer_queries(string $module, int $budget): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
        $class = 'App\\Livewire\\'.$module.'\\Index';
        $reference = 'reference'.$module;
        $expected = $this->$reference();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $actual = (new $class)->stats();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($expected, $actual);
        $this->assertLessThanOrEqual($budget, $count, $module.' statistics query budget');
    }

    #[DataProvider('modules')]
    public function test_empty_statistics_preserve_zero_values(string $module, int $budget): void
    {
        $class = 'App\\Livewire\\'.$module.'\\Index';
        $reference = 'reference'.$module;
        $this->assertSame($this->$reference(), (new $class)->stats());
    }

    public static function softDeleteModules(): array
    {
        return [['Correspondences', 1], ['Instruments', 1]];
    }

    #[DataProvider('softDeleteModules')]
    public function test_soft_deleted_rows_are_excluded(string $module, int $budget): void
    {
        $this->seed();
        $models = ['Performances' => Performance::class, 'Activities' => Activity::class, 'Correspondences' => Correspondence::class, 'Rehearsals' => Rehearsal::class, 'Instruments' => Instrument::class, 'Transactions' => Transaction::class, 'Contributions' => Contribution::class, 'Donations' => Donation::class];
        $model = $models[$module];
        $model::whereKey($model::query()->value('id'))->update(['deleted_at' => now()]);
        $class = 'App\\Livewire\\'.$module.'\\Index';
        $reference = 'reference'.$module;
        $this->assertSame($this->$reference(), (new $class)->stats());
    }

    private function referencePerformances(): array
    {
        $upcoming = Performance::where('scheduled_at', '>=', now())->count();

        return [
            'total' => Performance::count(),
            'avenir' => $upcoming,
            'budget' => (float) Performance::sum('budget'),
            'recettes' => (float) Performance::sum('revenue'),
        ];
    }

    private function referenceActivities(): array
    {
        return [
            'total' => Activity::count(),
            'planifiees' => Activity::where('status', ActivityStatus::Planifiee->value)->count(),
            'terminees' => Activity::where('status', ActivityStatus::Terminee->value)->count(),
            'a_venir' => Activity::where('starts_at', '>=', now()->startOfDay())->count(),
            'budget' => (float) Activity::sum('budget_allocated'),
            'depense' => (float) Activity::sum('budget_spent'),
        ];
    }

    private function referenceCorrespondences(): array
    {
        return [
            'total' => Correspondence::count(),
            'entrants' => Correspondence::where('type', CorrespondenceType::Entrant->value)->count(),
            'sortants' => Correspondence::where('type', CorrespondenceType::Sortant->value)->count(),
            'a_traiter' => Correspondence::whereIn('status', CorrespondenceStatus::open())->count(),
            'confidentiels' => Correspondence::where('confidentiality', 'confidentiel')->count(),
        ];
    }

    private function referenceRehearsals(): array
    {
        return [
            'total' => Rehearsal::count(),
            'avenir' => Rehearsal::whereDate('date', '>=', today())->count(),
            'terminees' => Rehearsal::where('status', EventStatus::Terminee->value)->count(),
            'presence' => (int) DB::table('rehearsal_attendances')->where('status', 'present')->count(),
        ];
    }

    private function referenceInstruments(): array
    {
        return [
            'total' => Instrument::count(),
            'quantite' => (int) Instrument::sum('quantity'),
            'valeur' => (float) Instrument::sum('current_value'),
            'horsservice' => Instrument::where('condition', InstrumentCondition::HorsService->value)->count(),
            'sortis' => Instrument::where('is_borrowed', true)->count(),
        ];
    }

    private function referenceTransactions(): array
    {
        $recettes = (float) Transaction::query()
            ->validated()
            ->where('type', TransactionType::Recette->value)
            ->sum('amount');

        $depenses = (float) Transaction::query()
            ->validated()
            ->where('type', TransactionType::Depense->value)
            ->sum('amount');

        return [
            'recettes' => $recettes,
            'depenses' => $depenses,
            'solde' => $recettes - $depenses,
            'brouillons' => Transaction::where('status', TransactionStatus::Brouillon->value)->count(),
            'total' => Transaction::count(),
        ];
    }

    private function referenceContributions(): array
    {
        $du = (float) Contribution::sum('due_amount');
        $encaisse = (float) Contribution::sum('paid_amount');

        return [
            'total' => Contribution::count(),
            'du' => $du,
            'encaisse' => $encaisse,
            'reste' => $du - $encaisse,
            'impayees' => Contribution::whereIn('status', [
                ContributionStatus::Due->value,
                ContributionStatus::Retard->value,
            ])->count(),
        ];
    }

    private function referenceDonations(): array
    {
        $total = (float) Donation::sum('amount');
        $count = Donation::count();

        $parType = Donation::query()
            ->whereNotNull('donation_type')
            ->selectRaw('donation_type, COUNT(*) as nombre, SUM(amount) as total')
            ->groupBy('donation_type')
            ->orderByDesc('total')
            ->first();

        return [
            'total' => $total,
            'nombre' => $count,
            'moyenne' => $count > 0 ? $total / $count : 0,
            'max' => (float) Donation::max('amount'),
            'type_dominant' => $parType?->donation_type?->value,
            'type_dominant_montant' => $parType ? (float) $parType->total : 0.0,
        ];
    }
}
