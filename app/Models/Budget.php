<?php

namespace App\Models;

use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    protected $fillable = [
        'choir_id',
        'year',
        'category',
        'allocated_amount',
        'committed_amount',
        'realized_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated_amount' => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'realized_amount' => 'decimal:2',
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Reste disponible sur la ligne budgétaire.
     */
    public function available(): float
    {
        return (float) $this->allocated_amount - (float) $this->realized_amount;
    }

    /**
     * Taux de consommation, en pourcentage.
     */
    public function consumptionRate(): float
    {
        if ((float) $this->allocated_amount <= 0) {
            return 0.0;
        }

        return round((float) $this->realized_amount / (float) $this->allocated_amount * 100, 1);
    }
}
