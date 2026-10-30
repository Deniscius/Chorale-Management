<?php

namespace App\Services;

use App\Models\ManagementYear;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

final class ManagementYearContext
{
    private ?Collection $years = null;
    private ?int $loadedTenantId = null;
    private ?ManagementYear $selection = null;
    private bool $selectionLoaded = false;

    private function key(): string
    {
        return app(TenantContext::class)->key('management-year-id');
    }

    /** A single database request per tenant and HTTP request, including an empty collection. */
    public function available(): Collection
    {
        $tenantId = app(TenantContext::class)->requireId();
        if ($this->years === null || $this->loadedTenantId !== $tenantId) {
            $this->years = ManagementYear::query()->where('is_active', true)
                ->orderByDesc('starts_on')->orderBy('type')->get();
            $this->loadedTenantId = $tenantId;
            $this->selection = null;
            $this->selectionLoaded = false;
        }

        return $this->years;
    }

    public function selected(): ?ManagementYear
    {
        $available = $this->available();
        if ($this->selectionLoaded) {
            return $this->selection;
        }

        $this->selectionLoaded = true;
        $id = Session::get($this->key());
        $this->selection = $id ? $available->firstWhere('id', (int) $id) : null;
        if ($this->selection) {
            return $this->selection;
        }
        if ($id) {
            Session::forget($this->key());
        }

        // Prefer 2026-2027 only when explicitly configured by the tenant.
        $this->selection = $available->first(fn (ManagementYear $year) =>
            $year->type === 'pastorale' && $year->label === '2026-2027'
        ) ?? $available->first(fn (ManagementYear $year) =>
            $year->starts_on->startOfDay()->lte(today())
            && $year->ends_on->endOfDay()->gte(today())
        );

        return $this->selection;
    }

    public function choose(int $id): ManagementYear
    {
        $year = $this->available()->firstWhere('id', $id);
        abort_unless($year, 404);

        Session::put($this->key(), $year->id);
        $this->selection = $year;
        $this->selectionLoaded = true;

        return $year;
    }
}
