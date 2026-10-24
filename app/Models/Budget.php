<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    protected $fillable = [
        'choir_id',
        'year',
        'category',
        'allocated_amount',
        'committed_amount',
        'realized_amount',
        'opening_realized_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated_amount' => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'realized_amount' => 'decimal:2',
            'opening_realized_amount' => 'decimal:2',
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
    public function actualRealized(): float
    {
        $posted = $this->getAttribute('posted_expenses');
        if (! array_key_exists('posted_expenses', $this->getAttributes())) {
            $posted = $this->transactions()->validated()->where('type', 'depense')->sum('amount');
        }

        return round((float) $this->opening_realized_amount + (float) $posted, 2);
    }

    public function scopeWithPostedExpenses(Builder $query): void
    {
        $query->withSum(['transactions as posted_expenses' => fn ($q) => $q->validated()->where('type', 'depense')], 'amount');
    }

    public function available(): float
    {
        return (float) $this->allocated_amount - $this->actualRealized();
    }

    /**
     * Taux de consommation, en pourcentage.
     */
    public function consumptionRate(): float
    {
        if ((float) $this->allocated_amount <= 0) {
            return 0.0;
        }

        return round($this->actualRealized() / (float) $this->allocated_amount * 100, 1);
    }
}
