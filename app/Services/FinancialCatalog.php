<?php

namespace App\Services;

use App\Enums\DonationType;
use App\Models\FinancialType;
use App\Models\Setting;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinancialCatalog
{
    private ?int $tenant = null;

    private ?Collection $memo = null;

    public static function seed(int $tenant): void
    {
        $defaults = [['code' => 'mensuelle', 'kind' => 'contribution', 'label' => 'Cotisation mensuelle', 'default_amount' => 2000, 'base_type' => null]];
        foreach (DonationType::cases() as $type) {
            $defaults[] = ['code' => $type->value, 'kind' => 'donation', 'label' => $type->label(), 'default_amount' => null, 'base_type' => $type->value];
        }
        foreach ($defaults as $row) {
            DB::table('financial_types')->insertOrIgnore($row + ['tenant_id' => $tenant, 'label_key' => mb_strtolower($row['label']), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function all(): Collection
    {
        $tenant = app(TenantContext::class)->requireId();
        if ($this->tenant !== $tenant) {
            $this->forget();
            $this->tenant = $tenant;
        }

        return $this->memo ??= FinancialType::orderBy('id')->get();
    }

    public function options(string $kind, ?string $current = null, bool $includeArchived = false): array
    {
        return $this->all()->where('kind', $kind)->filter(fn ($type) => $includeArchived || $type->is_active || $type->code === $current)
            ->mapWithKeys(fn ($type) => [$type->code => $type->label.($type->is_active ? '' : ' (désactivé)')])->all();
    }

    public function label(?string $code, string $fallback = '—'): string
    {
        return $this->all()->firstWhere('code', $code)?->label ?? $fallback;
    }

    public function section(string $kind): string
    {
        return Setting::get('finance.label.'.$kind, $kind === 'contribution' ? 'Cotisations' : 'Dons');
    }

    public function forWrite(string $kind, string $code, ?string $current = null): FinancialType
    {
        $type = FinancialType::where('kind', $kind)->where('code', $code)->sharedLock()->first();
        if (! $type || (! $type->is_active && $code !== $current)) {
            throw ValidationException::withMessages([$kind === 'contribution' ? 'type_code' : 'donation_type' => 'Choisissez un type actif de votre chorale.']);
        }

        return $type;
    }

    public function forget(): void
    {
        $this->memo = null;
    }
}
