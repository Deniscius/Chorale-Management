<?php

namespace App\Services;

use App\Models\ManagementYear;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Session;

final class ManagementYearContext
{
    private function key(): string
    {
        return app(TenantContext::class)->key('management-year-id');
    }

    public function selected(): ?ManagementYear
    {
        $id = Session::get($this->key());

        if ($id) {
            $selected = ManagementYear::query()->where('is_active', true)->find($id);
            if ($selected) {
                return $selected;
            }
            Session::forget($this->key());
        }

        // Default preference is pastoral year 2026-2027, if its dates
        // have already been configured by this tenant's administrator.
        return ManagementYear::query()->where('is_active', true)
            ->where('type', 'pastorale')->where('label', '2026-2027')->first()
            ?? ManagementYear::query()->where('is_active', true)
                ->whereDate('starts_on', '<=', today())
                ->whereDate('ends_on', '>=', today())
                ->orderByRaw("CASE WHEN type = 'pastorale' THEN 0 ELSE 1 END")->first();
    }

    public function choose(int $id): ManagementYear
    {
        $year = ManagementYear::query()->where('is_active', true)->findOrFail($id);
        Session::put($this->key(), $year->id);

        return $year;
    }

    public function available()
    {
        return ManagementYear::query()->where('is_active', true)
            ->orderByDesc('starts_on')->orderBy('type')->get();
    }
}
