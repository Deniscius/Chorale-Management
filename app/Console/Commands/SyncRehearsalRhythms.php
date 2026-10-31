<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\RehearsalPlanner;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class SyncRehearsalRhythms extends Command
{
    protected $signature = 'chorale:sync-rehearsals {--days=45 : Horizon glissant de programmation} {--tenant= : Espace ciblé}';

    protected $description = 'Programme sans doublon les répétitions récurrentes des chorales actives';

    public function handle(RehearsalPlanner $planner, TenantContext $context): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 366) {
            $this->error('Le nombre de jours doit être compris entre 1 et 366.');
            return self::INVALID;
        }
        $from = CarbonImmutable::today();
        $until = $from->addDays($days);
        $count = 0;

        if (! TenantContext::isolated()) {
            $count = $planner->generate($from, $until);
        } else {
            Tenant::query()->when($this->option('tenant'), fn ($q) => $q->whereKey($this->option('tenant')))
                ->cursor()->each(function (Tenant $tenant) use ($context, $planner, $from, $until, &$count): void {
                    if ($tenant->hasAccess()) {
                        $count += $context->run($tenant->id, fn () => $planner->generate($from, $until));
                    }
                });
        }
        $this->info($count.' répétition(s) planifiée(s), sans modification des séances existantes.');
        return self::SUCCESS;
    }
}
