<?php

namespace App\Services;

use App\Mail\MailDeliveryRejected;

final class MailConfiguration
{
    /** Values are statuses only: credentials are never returned. */
    public function checks(): array
    {
        $driver = config('mail.default');
        $credentials = match ($driver) {
            'brevo' => filled(config('mail.mailers.brevo.key')),
            'smtp' => filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password')),
            'sendmail' => filled(config('mail.mailers.sendmail.path')),
            default => ! in_array($driver, ['log', 'array', 'failover', null, ''], true),
        };

        return [
            'Livraison activée (MAIL_DELIVERY_ENABLED)' => (bool) config('mail.delivery_enabled'),
            'Transport réel sans secours vers les journaux (MAIL_MAILER)' => ! in_array($driver, ['log', 'array', 'failover', null, ''], true),
            'Identifiants du transport renseignés' => $credentials,
            'Adresse d’expédition valide (MAIL_FROM_ADDRESS)' => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false
                && ! str_ends_with((string) config('mail.from.address'), '@example.com'),
        ];
    }

    public function assertReady(): void
    {
        $failed = array_keys(array_filter($this->checks(), fn ($passed) => ! $passed));
        if ($failed) {
            throw new MailDeliveryRejected('Envoi e-mail indisponible : '.implode(' ; ', $failed).'.');
        }
    }
}
