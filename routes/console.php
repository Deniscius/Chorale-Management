<?php

use App\Console\Commands\SendRehearsalReminders;
use App\Models\Tenant;
use App\Services\ReminderDispatcher;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rappels de répétition : chaque matin, à l'heure configurée.
Schedule::command(SendRehearsalReminders::class)
    ->dailyAt(config('chorale.reminders.send_at', '08:00'))
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => (bool) config('chorale.reminders.enabled'));

Schedule::call(function () {
    if (! config('tenancy.enabled')) {
        return app(ReminderDispatcher::class)->recoverStale();
    }
    foreach (Tenant::cursor() as $tenant) {
        if ($tenant->hasAccess()) {
            app(TenantContext::class)->run($tenant->id, fn () => app(ReminderDispatcher::class)->recoverStale());
        }
    }
})
    ->name('chorale:recover-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
