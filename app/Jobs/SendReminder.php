<?php

namespace App\Jobs;

use App\Jobs\Middleware\TenantJob;
use App\Models\Tenant;
use App\Services\ReminderDispatcher;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

final class SendReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public ?int $tenantId = null;

    public function __construct(public int $reminderId, public string $leaseToken, public string $channel)
    {
        $this->tenantId = app(TenantContext::class)->id();
    }

    public function middleware(): array
    {
        return [new TenantJob, new RateLimited('reminder-'.$this->channel)];
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(ReminderDispatcher $dispatcher): void
    {
        if (TenantContext::isolated() && ($this->tenantId === null || app(TenantContext::class)->id() !== $this->tenantId || ! Tenant::find($this->tenantId)?->hasAccess())) {
            return;
        }
        if ($dispatcher->deliver($this->reminderId, $this->leaseToken, true) === 'failed') {
            throw new \RuntimeException('Échec d’envoi du rappel ; consulter le journal.');
        }
    }
}
