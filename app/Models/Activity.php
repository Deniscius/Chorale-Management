<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Tenancy\BelongsToTenant;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'choir_id',
        'title',
        'type',
        'description',
        'starts_at',
        'ends_at',
        'venue',
        'organizer_id',
        'choir_commission_id',
        'budget_allocated',
        'budget_spent',
        'status',
        'report',
        'is_public',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'status' => ActivityStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'budget_allocated' => 'decimal:2',
            'budget_spent' => 'decimal:2',
            'is_public' => 'boolean',
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'organizer_id');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(ChoirCommission::class, 'choir_commission_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'activity_participants')
            ->withPivot(['role', 'status', 'hours', 'notes'])
            ->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Solde disponible sur l'activité.
     */
    public function remainingBudget(): float
    {
        return (float) $this->budget_allocated - (float) $this->budget_spent;
    }
}
