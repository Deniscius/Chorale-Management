<?php

namespace App\Console\Commands;

use App\Services\MailConfiguration;
use Illuminate\Console\Command;

final class CheckMailConfiguration extends Command
{
    protected $signature = 'chorale:check-mail';

    protected $description = 'Diagnostiquer la configuration effective des e-mails sans envoyer de message ni afficher de secret';

    public function handle(MailConfiguration $configuration): int
    {
        $checks = $configuration->checks();
        $this->table(['Contrôle', 'État'], collect($checks)->map(fn ($passed, $label) => [$label, $passed ? 'OK' : 'À corriger'])->values()->all());
        $this->line('Transport : '.config('mail.default').'. File : '.config('queue.default').'.');
        $this->line('Configuration chargée par ce processus. Aucun e-mail envoyé. Vérifier séparément le domaine, les événements Brevo et la boîte destinataire.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
