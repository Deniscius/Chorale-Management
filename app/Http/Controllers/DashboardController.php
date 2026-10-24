<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
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
     * mutualisées pour tous les utilisateurs et servies depuis le cache.
     *
     * Le rafraîchissement est « stale-while-revalidate » : les données sont
     * considérées fraîches 30 secondes, puis l'utilisateur reçoit la version
     * en cache (jusqu'à 2 minutes) pendant qu'elle est recalculée en arrière-plan.
     */
    public function __invoke(): View
    {
        $tenantId = app(TenantContext::class)->requireId();

        return view('dashboard', Cache::flexible(
            app(TenantContext::class)->key('dashboard.overview.v1'),
            [30, 120],
            fn (): array => app(TenantContext::class)->run($tenantId, fn () => $this->overview()),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function overview(): array
    {
        $now = now();

        $recettes = Transaction::ofType(TransactionType::Recette)
            ->whereIn('status', [TransactionStatus::Validee->value, TransactionStatus::Cloturee->value])
            ->sum('amount');

        $depenses = Transaction::ofType(TransactionType::Depense)
            ->whereIn('status', [TransactionStatus::Validee->value, TransactionStatus::Cloturee->value])
            ->sum('amount');

        return [
            'kpis' => [
                'choristes' => Member::where('status', MemberStatus::Actif->value)->count(),
                'partitions' => Work::where('is_repertoire', true)->count(),
                'repetitions' => Rehearsal::whereDate('date', '>=', $now->toDateString())->count(),
                'concerts' => Performance::where('scheduled_at', '>=', $now)->count(),
                'courriers' => Correspondence::open()->count(),
                'activites' => Activity::whereIn('status', ['planifiee', 'en_cours'])->count(),
                'instruments' => Instrument::count(),
                'solde' => $recettes - $depenses,
            ],
            // Seul le nombre d'œuvres est affiché : un COUNT évite de charger
            // les modèles complets de chaque répétition.
            'prochainesRepetitions' => Rehearsal::query()
                ->withCount('works')
                ->whereDate('date', '>=', $now->toDateString())
                ->orderBy('date')
                ->limit(5)
                ->get(),
            'prochainesCelebrations' => Performance::query()
                ->where('scheduled_at', '>=', $now)
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get(),
            'derniersCourriers' => Correspondence::with('service')
                ->latest('correspondence_date')
                ->limit(5)
                ->get(),
            'activitesRecentes' => Activity::orderByDesc('starts_at')->limit(5)->get(),
        ];
    }
}
