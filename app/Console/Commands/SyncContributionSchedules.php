<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ContributionSchedule;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

final class SyncContributionSchedules extends Command
{
    protected $signature = 'chorale:sync-contributions';

    protected $description = 'Génère les échéances de cotisation arrivées à terme pour chaque chorale';

    public function handle(ContributionSchedule $schedule, TenantContext $context): int
    {
        $created = 0;
        if (! TenantContext::isolated()) {
            $created = $schedule->sync();
        } else {
            foreach (Tenant::cursor() as $tenant) {
                if ($tenant->hasAccess()) {
                    $created += $context->run($tenant->id, fn () => $schedule->sync());
                }
            }
        }
        $this->info($created.' échéance(s) créée(s).');

        return self::SUCCESS;
    }
}
