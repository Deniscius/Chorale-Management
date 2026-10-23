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
     * « sms_driver »  : « log » (développement) ou « http » (fournisseur).
     */
    'reminders' => [
        'enabled' => (bool) env('REMINDERS_ENABLED', true),
        'days_before' => (int) env('REMINDERS_DAYS_BEFORE', 2),
        'send_at' => env('REMINDERS_SEND_AT', '08:00'),
        'sms_enabled' => (bool) env('REMINDERS_SMS_ENABLED', false),
        'sms_driver' => env('REMINDERS_SMS_DRIVER', 'log'),
        'sms_from' => env('REMINDERS_SMS_FROM', 'CHORALE'),
    ],

    'sms' => [
        'endpoint' => env('SMS_ENDPOINT'),
        'token' => env('SMS_TOKEN'),
        'timeout' => (int) env('SMS_TIMEOUT', 15),
    ],

];
