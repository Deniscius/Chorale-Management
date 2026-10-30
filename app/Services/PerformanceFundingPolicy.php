<?php

namespace App\Services;

use App\Enums\PerformanceType;
use App\Models\Setting;

final class PerformanceFundingPolicy
{
    public function defaultFor(PerformanceType|string $type): float
    {
        $value = $type instanceof PerformanceType ? $type->value : $type;

        return max(0, (float) Setting::get('finance.performance_budget.'.$value, 0));
    }

    /** @return array<string, float> */
    public function all(): array
    {
        return collect(PerformanceType::cases())->mapWithKeys(
            fn (PerformanceType $type) => [$type->value => $this->defaultFor($type)]
        )->all();
    }
}
