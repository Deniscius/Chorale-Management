<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Correspondence;
use App\Models\Instrument;
use App\Models\Member;
use App\Models\Performance;
use App\Models\Rehearsal;
use App\Models\Transaction;
use App\Models\Work;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Le tableau de bord agrège de nombreuses statistiques : elles sont
     * mutualisées par espace et ensemble de droits, puis servies depuis le cache.
     *
     * Le rafraîchissement est « stale-while-revalidate » : les données sont
     * considérées fraîches 30 secondes, puis l'utilisateur reçoit la version
     * en cache (jusqu'à 2 minutes) pendant qu'elle est recalculée en arrière-plan.
     */
    public function __invoke(): View
    {
        $tenantId = app(TenantContext::class)->requireId();
        $modules = auth()->user()->accessibleModules();
        sort($modules);

        return view('dashboard', Cache::flexible(
            app(TenantContext::class)->key('dashboard.overview.v3.'.hash('sha256', implode('|', $modules))),
            [30, 120],
            fn (): array => app(TenantContext::class)->run($tenantId, fn () => $this->overview($modules)),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function overview(array $modules): array
    {
        $now = now();

        $allowed = fn (string $module): bool => in_array($module, $modules, true);
        $liturgical = $allowed(UserRole::MODULE_LITURGIQUE);
        $mail = $allowed(UserRole::MODULE_COURRIERS);
        $activities = $allowed(UserRole::MODULE_ACTIVITES);
        $upcoming = ['planifiee', 'en_cours', 'reportee'];
        $balance = $allowed(UserRole::MODULE_COMPTABILITE) ? Transaction::query()
            ->whereIn('status', [TransactionStatus::Validee->value, TransactionStatus::Cloturee->value])
            ->whereIn('type', [TransactionType::Recette->value, TransactionType::Depense->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) AS balance', [TransactionType::Recette->value])
            ->toBase()->first()->balance : 0;

        return [
            'kpis' => [
                'choristes' => $allowed(UserRole::MODULE_MEMBERS) ? Member::where('status', MemberStatus::Actif->value)->count() : 0,
                'partitions' => $liturgical ? Work::where('is_repertoire', true)->count() : 0,
                'repetitions' => $liturgical ? Rehearsal::whereIn('status', $upcoming)->whereDate('date', '>=', $now->toDateString())->count() : 0,
                'concerts' => $liturgical ? Performance::whereIn('status', $upcoming)->where('scheduled_at', '>=', $now)->count() : 0,
                'courriers' => $mail ? Correspondence::open()->count() : 0,
                'activites' => $activities ? Activity::whereIn('status', ['planifiee', 'en_cours'])->count() : 0,
                'instruments' => $allowed(UserRole::MODULE_INSTRUMENTS) ? Instrument::count() : 0,
                'solde' => $balance,
            ],
            // Seul le nombre d'œuvres est affiché : un COUNT évite de charger
            // les modèles complets de chaque répétition.
            'prochainesRepetitions' => $liturgical ? Rehearsal::query()
                ->select(['id', 'tenant_id', 'date', 'starts_at', 'ends_at', 'venue'])
                ->withCount('works')
                ->whereIn('status', $upcoming)
                ->whereDate('date', '>=', $now->toDateString())
                ->orderBy('date')
                ->limit(5)
                ->get() : collect(),
            'prochainesCelebrations' => $liturgical ? Performance::query()
                ->select(['id', 'tenant_id', 'title', 'scheduled_at', 'venue', 'type'])
                ->whereIn('status', $upcoming)
                ->where('scheduled_at', '>=', $now)
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get() : collect(),
            'derniersCourriers' => $mail ? Correspondence::with('service:id,tenant_id,name')
                ->select(['id', 'tenant_id', 'service_id', 'reference', 'subject', 'sender_name', 'correspondence_date', 'status'])
                ->latest('correspondence_date')
                ->limit(5)
                ->get() : collect(),
            'activitesRecentes' => $activities ? Activity::select(['id', 'tenant_id', 'title', 'starts_at', 'venue', 'status'])->orderByDesc('starts_at')->limit(5)->get() : collect(),
        ];
    }
}
