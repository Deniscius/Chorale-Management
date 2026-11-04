<?php

namespace App\Console\Commands;

use App\Models\Rehearsal;
use App\Models\Tenant;
use App\Services\PlatformReminderDispatcher;
use App\Services\ReminderDispatcher;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Envoie les rappels de répétition.
 *
 * La commande est prévue pour être planifiée chaque matin. Elle peut aussi
 * être lancée manuellement pour vérifier les messages avant diffusion
 * (`--dry-run`) ou forcer un envoi ciblé pour une répétition précise.
 */
class SendRehearsalReminders extends Command
{
    protected $signature = 'chorale:send-reminders
        {--dry-run : Afficher les rappels sans rien envoyer}
        {--tenant= : Espace client à traiter (tous les espaces actifs par défaut)}
        {--rehearsal= : Ne traiter que la répétition indiquée (identifiant)}
        {--platform-only : Traiter seulement les rappels transversaux}
        {--rehearsals-only : Traiter seulement les rappels de répétition}';

    protected $description = 'Envoie les rappels de la chorale et les échéances personnelles';

    public function handle(ReminderDispatcher $dispatcher, PlatformReminderDispatcher $platform): int
    {
        if ($this->option('platform-only') && $this->option('rehearsals-only')) {
            $this->error('Choisissez soit les rappels transversaux, soit les répétitions.');

            return self::INVALID;
        }
        if (! TenantContext::isolated()) {
            return $this->handleOne($dispatcher, $platform);
        }
        if ($this->option('rehearsal') && ! $this->option('tenant')) {
            $this->error('Précisez --tenant pour une répétition ciblée.');

            return self::INVALID;
        }
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q) => $q->whereKey($this->option('tenant')))->cursor();
        $exit = self::SUCCESS;
        foreach ($tenants as $tenant) {
            if ($tenant->hasAccess()) {
                $exit = max($exit, app(TenantContext::class)->run($tenant->id, fn () => $this->handleOne($dispatcher, $platform)));
            }
        }

        return $exit;
    }

    private function handleOne(ReminderDispatcher $dispatcher, PlatformReminderDispatcher $platform): int
    {
        if (! config('chorale.reminders.enabled')) {
            $this->components->warn('Les rappels sont désactivés (REMINDERS_ENABLED=false).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $platformOnly = (bool) $this->option('platform-only');
            $rehearsalsOnly = (bool) $this->option('rehearsals-only');
            if ($platformOnly) {
                $result = ['rehearsals' => 0, 'sent' => 0, 'queued' => 0, 'skipped' => 0, 'failed' => 0];
                $platformResult = $platform->dispatchAll($dryRun);
            } elseif ($rehearsalId = $this->option('rehearsal')) {
                $rehearsal = Rehearsal::findOrFail($rehearsalId);
                $result = $dispatcher->dispatchForRehearsal($rehearsal, $dryRun);
                $result['rehearsals'] = 1;
            } else {
                $result = $dispatcher->dispatchAll($dryRun);
                if (! $rehearsalsOnly) {
                    $platformResult = $platform->dispatchAll($dryRun);
                }
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%d répétition(s) traitée(s) — %d envoi(s), %d mis en file, %d ignoré(s), %d échec(s)%s.',
            $result['rehearsals'],
            $result['sent'],
            $result['queued'],
            $result['skipped'],
            $result['failed'],
            $dryRun ? ' (simulation)' : '',
        ));

        if (isset($platformResult)) {
            $this->components->info(sprintf(
                '%d rappel(s) transversal(aux) détecté(s) — %d envoyé(s), %d déjà traité(s), %d échec(s)%s.',
                $platformResult['candidates'], $platformResult['sent'], $platformResult['skipped'], $platformResult['failed'],
                $dryRun ? ' (simulation)' : '',
            ));
            $result['failed'] += $platformResult['failed'];
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
