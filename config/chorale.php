<?php

/**
 * Paramètres métier de la chorale.
 */
return [

    /*
     * Rappels de répétition.
     *
     * « days_before » : nombre de jours entre l'envoi et la répétition.
     * « sms_enabled » : active l'envoi par SMS (nécessite un fournisseur).
     * « sms_driver »  : « log » (développement), « http » (passerelle) ou « d7 » (D7 Networks).
     */
    'reminders' => [
        'enabled' => (bool) env('REMINDERS_ENABLED', true),
        'queued' => (bool) env('REMINDERS_QUEUED', true),
        'lease_minutes' => (int) env('REMINDERS_LEASE_MINUTES', 60),
        'days_before' => (int) env('REMINDERS_DAYS_BEFORE', 2),
        'platform_days' => array_map('intval', explode(',', env('REMINDERS_PLATFORM_DAYS', '7,2,0'))),
        'day_of_reminder_hours' => array_map('intval', explode(',', env('REMINDERS_DAY_OF_HOURS', '8,12,17'))),
        'notebook_days' => (int) env('PERSONAL_NOTEBOOK_DAYS', 45),
        'send_at' => env('REMINDERS_SEND_AT', '08:00'),
        'oidc_audience' => env('REMINDERS_OIDC_AUDIENCE', 'chorale-management-reminders'),
        'oidc_repository' => env('REMINDERS_OIDC_REPOSITORY', 'Deniscius/Chorale-Management'),
        'sms_enabled' => (bool) env('REMINDERS_SMS_ENABLED', false),
        'sms_driver' => env('REMINDERS_SMS_DRIVER', 'log'),
        'sms_from' => env('REMINDERS_SMS_FROM', 'CHORALE'),
    ],

    'sms' => [
        'endpoint' => env('SMS_ENDPOINT'),
        'token' => env('SMS_TOKEN'),
        'timeout' => (int) env('SMS_TIMEOUT', 15),
        'd7' => [
            'token' => env('D7_API_TOKEN'),
            'originator' => env('D7_SMS_ORIGINATOR', env('REMINDERS_SMS_FROM', 'CHORALE')),
        ],
    ],

];
