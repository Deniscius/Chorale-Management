<?php

namespace App\Models;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'un rappel envoyé à un choriste.
 *
 * Une ligne est créée avant l'envoi (statut « en_attente ») puis mise à jour
 * avec le résultat. La contrainte d'unicité (répétition, choriste, canal)
 * garantit qu'un rappel n'est jamais envoyé deux fois.
 */
class Reminder extends Model
{
    protected $fillable = [
        'rehearsal_id',
        'member_id',
        'channel',
        'recipient',
        'subject',
        'body',
        'status',
        'sent_at',
        'error',
        'lease_token', 'reserved_at', 'sending_at', 'attempts',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ReminderChannel::class,
            'status' => ReminderStatus::class,
            'sent_at' => 'datetime',
            'reserved_at' => 'datetime', 'sending_at' => 'datetime', 'attempts' => 'integer',
        ];
    }

    public function rehearsal(): BelongsTo
    {
        return $this->belongsTo(Rehearsal::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Scope : rappels effectivement envoyés.
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', ReminderStatus::Envoye->value);
    }

    /**
     * Scope : rappels en échec.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', ReminderStatus::Echec->value);
    }
}
