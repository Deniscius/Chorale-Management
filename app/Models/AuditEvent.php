<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuditEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Normalise également les événements historiques ou partiellement écrits.
     * Le journal reste consultable même si une ancienne ligne ne possède pas
     * encore la structure {before, after} actuelle.
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function changeSet(): array
    {
        // Eloquent utilise déjà une propriété interne $changes : accéder
        // explicitement à l'attribut JSON évite cette collision de nom.
        $changes = $this->getAttribute('changes');
        if (! is_array($changes)) {
            return ['before' => [], 'after' => []];
        }

        if (! array_key_exists('before', $changes) && ! array_key_exists('after', $changes)) {
            return ['before' => [], 'after' => $changes];
        }

        return [
            'before' => is_array($changes['before'] ?? null) ? $changes['before'] : [],
            'after' => is_array($changes['after'] ?? null) ? $changes['after'] : [],
        ];
    }

    public static function displayValue(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'Oui' : 'Non',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—',
        };
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Le journal est en ajout uniquement.'));
        self::deleting(fn () => throw new \LogicException('Le journal est en ajout uniquement.'));
    }
}
