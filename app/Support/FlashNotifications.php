<?php

namespace App\Support;

final class FlashNotifications
{
    /** @return array<int, array{id: string, type: string, message: string}> */
    public function all(): array
    {
        $notifications = [];
        $status = session('status');

        if (is_string($status) && $status !== '') {
            $notifications[] = $this->notification('success', match ($status) {
                'profile-updated' => 'Vos informations personnelles ont été enregistrées.',
                'password-updated' => 'Votre mot de passe a été modifié.',
                'manual-reset-required' => 'Contactez un administrateur de votre chorale pour recevoir un lien personnel de réinitialisation.',
                'verification-link-sent' => 'Un nouveau lien de vérification a été envoyé à votre adresse e-mail.',
                default => $status,
            });
        }

        foreach ([
            'letterhead_success' => 'success',
            'success' => 'success',
            'billing_error' => 'error',
            'error' => 'error',
            'warning' => 'warning',
        ] as $key => $type) {
            $message = session($key);
            if (is_string($message) && $message !== '') {
                $notifications[] = $this->notification($type, $message);
            }
        }

        $toast = session('toast');
        if (is_array($toast) && is_string($toast['message'] ?? null) && $toast['message'] !== '') {
            $notifications[] = $this->notification(
                in_array($toast['type'] ?? null, ['success', 'error', 'warning', 'info'], true) ? $toast['type'] : 'info',
                $toast['message'],
            );
        }

        return array_slice($notifications, -4);
    }

    /** @return array{id: string, type: string, message: string} */
    private function notification(string $type, string $message): array
    {
        return [
            'id' => 'flash-'.substr(hash('sha256', $type."\0".$message), 0, 12),
            'type' => $type,
            'message' => $message,
        ];
    }
}
