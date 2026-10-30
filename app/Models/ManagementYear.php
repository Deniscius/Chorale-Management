<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ManagementYear extends Model
{
    use BelongsToTenant;

    protected $fillable = ['type', 'label', 'starts_on', 'ends_on', 'theme', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function validatedAttributes(array $data): array
    {
        $data = validator($data, [
            'type' => ['required', 'in:civile,pastorale'],
            'label' => ['required', 'string', 'max:64'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'theme' => ['nullable', 'string', 'max:255', 'required_if:type,pastorale'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();

        if ($data['type'] === 'civile') {
            $start = CarbonImmutable::parse($data['starts_on']);
            $end = CarbonImmutable::parse($data['ends_on']);
            if (! ($start->month === 1 && $start->day === 1) || ! $end->isSameDay($start->endOfYear())) {
                throw ValidationException::withMessages([
                    'starts_on' => 'Une année civile doit commencer le 1er janvier et se terminer le 31 décembre de la même année.',
                ]);
            }
        }

        return $data;
    }
}
