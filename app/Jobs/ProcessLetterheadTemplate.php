<?php

namespace App\Jobs;

use App\Models\LetterheadTemplate;
use App\Services\LetterheadProcessor;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessLetterheadTemplate implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public readonly int $tenantId, public readonly int $templateId) {}

    public function handle(TenantContext $tenant, LetterheadProcessor $processor): void
    {
        $tenant->run($this->tenantId, function () use ($processor): void {
            $processor->process(LetterheadTemplate::query()->findOrFail($this->templateId));
        });
    }
}
