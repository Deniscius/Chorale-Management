<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\PerformanceType;
use App\Tenancy\BelongsToTenant;
use Database\Factories\PerformanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Performance extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PerformanceFactory> */
    use HasFactory;

    protected $fillable = [
        'choir_id',
        'title',
        'type',
        'scheduled_at',
        'venue',
        'audience',
        'status',
        'budget',
        'revenue',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => PerformanceType::class,
            'status' => EventStatus::class,
            'scheduled_at' => 'datetime',
            'budget' => 'decimal:2',
            'revenue' => 'decimal:2',
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function works(): BelongsToMany
    {
        return $this->belongsToMany(Work::class)
            ->withPivot(['order', 'role', 'choir_part', 'duration_minutes', 'notes'])
            ->withTimestamps()
            ->orderBy('performance_work.order');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Durée totale estimée du programme.
     */
    public function programDuration(): int
    {
        return (int) $this->works->sum(
            fn ($work) => $work->pivot->duration_minutes ?? $work->duration_minutes ?? 0
        );
    }
}
