<?php

namespace App\Console\Commands;

use App\Models\Rehearsal;
use App\Services\ReminderDispatcher;
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
        {--rehearsal= : Ne traiter que la répétition indiquée (identifiant)}';

    protected $description = 'Envoie les rappels de répétition par e-mail et par SMS';

    public function handle(ReminderDispatcher $dispatcher): int
    {
        if (! config('chorale.reminders.enabled')) {
            $this->components->warn('Les rappels sont désactivés (REMINDERS_ENABLED=false).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            if ($rehearsalId = $this->option('rehearsal')) {
                $rehearsal = Rehearsal::findOrFail($rehearsalId);
                $result = $dispatcher->dispatchForRehearsal($rehearsal, $dryRun);
                $result['rehearsals'] = 1;
            } else {
                $result = $dispatcher->dispatchAll($dryRun);
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

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
