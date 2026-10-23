<?php

namespace App\Models;

use App\Enums\InstrumentCondition;
use App\Enums\MovementType;
use Database\Factories\InstrumentMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentMovement extends Model
{
    /** @use HasFactory<InstrumentMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'instrument_id',
        'type',
        'moved_on',
        'reason',
        'destination',
        'responsible_id',
        'expected_return_on',
        'actual_return_on',
        'condition_on_return',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'moved_on' => 'date',
            'expected_return_on' => 'date',
            'actual_return_on' => 'date',
            'condition_on_return' => InstrumentCondition::class,
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'responsible_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Le mouvement est-il clôturé ?
     */
    public function isClosed(): bool
    {
        return filled($this->actual_return_on);
    }

    /**
     * Le retour est-il en retard ?
     */
    public function isOverdue(): bool
    {
        return ! $this->isClosed()
            && filled($this->expected_return_on)
            && $this->expected_return_on->isPast();
    }
}
