<?php

namespace App\Models;

use App\Services\FinancialCatalog;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FinancialType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['label', 'default_amount', 'frequency_months', 'starts_on', 'due_day', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_amount' => 'decimal:2',
            'frequency_months' => 'integer',
            'starts_on' => 'date',
            'due_day' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $type) {
            if ($type->exists && $type->isDirty(['kind', 'code', 'base_type'])) {
                throw ValidationException::withMessages(['label' => 'La rubrique et la nature comptable restent fixes. Créez un nouveau type si nécessaire.']);
            }
            $type->label = Str::squish($type->label);
            $type->forceFill(['label_key' => mb_strtolower($type->label)]);
        });
        self::saved(fn () => app(FinancialCatalog::class)->forget());
    }
}
