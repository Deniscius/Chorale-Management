<?php

use App\Console\Commands\SendRehearsalReminders;
use App\Console\Commands\SyncContributionSchedules;
use App\Console\Commands\SyncSubscriptions;
use App\Models\Tenant;
use App\Services\ReminderDispatcher;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => DB::table('platform_usage_daily')->where('day', '<', today()->subDays(90)->toDateString())->delete())->dailyAt('03:30')->name('platform:prune-usage')->withoutOverlapping();

Schedule::command(SyncSubscriptions::class)->everyFiveMinutes()->withoutOverlapping(30)
    ->when(fn () => (bool) config('tenancy.chariow.api_key') && (bool) config('tenancy.chariow.product_id'));

Schedule::command(SyncContributionSchedules::class)
    ->dailyAt('00:15')->withoutOverlapping()->onOneServer();

// Rappels de répétition : chaque matin, à l'heure configurée.
Schedule::command(SendRehearsalReminders::class)
    ->dailyAt(config('chorale.reminders.send_at', '08:00'))
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => (bool) config('chorale.reminders.enabled'));

Schedule::call(function () {
    if (! TenantContext::isolated()) {
        return app(ReminderDispatcher::class)->recoverStale();
    }
    foreach (Tenant::cursor() as $tenant) {
        if ($tenant->hasAccess()) {
            app(TenantContext::class)->run($tenant->id, fn () => app(ReminderDispatcher::class)->recoverStale());
        }
    }
})
    ->name('chorale:recover-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
