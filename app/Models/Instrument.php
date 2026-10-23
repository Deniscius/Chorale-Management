<?php

namespace App\Models;

use App\Enums\InstrumentCategory;
use App\Enums\InstrumentCondition;
use Database\Factories\InstrumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instrument extends Model
{
    /** @use HasFactory<InstrumentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'choir_id',
        'name',
        'category',
        'serial_number',
        'quantity',
        'condition',
        'purchase_on',
        'purchase_price',
        'current_value',
        'location',
        'custodian_id',
        'is_borrowed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => InstrumentCategory::class,
            'condition' => InstrumentCondition::class,
            'purchase_on' => 'date',
            'purchase_price' => 'decimal:2',
            'current_value' => 'decimal:2',
            'quantity' => 'integer',
            'is_borrowed' => 'boolean',
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'custodian_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InstrumentMovement::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(InstrumentMaintenance::class);
    }

    public function scopeOutOfService(Builder $query): Builder
    {
        return $query->where('condition', InstrumentCondition::HorsService->value);
    }

    /**
     * Sorties non clôturées (prêts en cours).
     */
    public function availableQuantity(): int
    {
        $out = array_key_exists('loaned_quantity', $this->getAttributes()) ? ($this->getAttribute('loaned_quantity') ?? 0) : $this->movements()->whereIn('type', ['sortie', 'pret'])->whereNull('actual_return_on')->sum('quantity');

        return max(0, $this->quantity - (int) $out);
    }

    public function pendingReturns(): int
    {
        return $this->movements()
            ->whereIn('type', ['sortie', 'pret'])
            ->whereNull('actual_return_on')
            ->count();
    }
}
