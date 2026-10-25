<?php

namespace App\Console\Commands;

use App\Services\ChariowLicense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SyncSubscriptions extends Command
{
    protected $signature = 'chorale:sync-subscriptions {--limit=100 : Maximum de licences à vérifier}';

    protected $description = 'Actualiser les échéances des abonnements depuis Chariow sans déclencher de paiement';

    public function handle(ChariowLicense $licenses): int
    {
        if (! config('tenancy.chariow.api_key') || ! config('tenancy.chariow.product_id')) {
            $this->error('Chariow doit être configuré avant la synchronisation.');

            return self::FAILURE;
        }
        $limit = (string) $this->option('limit');
        if (! preg_match('/^\d{1,4}$/D', $limit) || (int) $limit < 1 || (int) $limit > 1000) {
            $this->error('La limite doit être comprise entre 1 et 1000.');

            return self::INVALID;
        }
        $ids = DB::table('tenant_licenses')
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinutes(15)))
            ->orderByRaw('CASE WHEN last_checked_at IS NULL THEN 0 ELSE 1 END')->orderBy('last_checked_at')->orderBy('id')
            ->limit((int) $limit)->pluck('id');
        $success = $failed = $consecutive = 0;
        foreach ($ids as $id) {
            if ($licenses->sync((int) $id)) {
                $success++;
                $consecutive = 0;
            } else {
                $failed++;
                if (++$consecutive >= 3) {
                    break;
                }
            }
        }
        $this->line('Licences vérifiées : '.$success.' ; à vérifier : '.$failed.'. Aucun paiement déclenché.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
