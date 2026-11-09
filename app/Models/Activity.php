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
use Illuminate\Validation\ValidationException;

class Activity extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (self $activity): void {
            if ($activity->transactions()->exists() || $activity->participationContributions()->exists() || $activity->memberExpenses()->exists()) {
                throw ValidationException::withMessages(['operation' => 'Cette activité possède un historique financier. Conservez-la et utilisez son statut plutôt que de la supprimer.']);
            }
        });
    }

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
        'participation_fee_enabled',
        'participation_fee_amount',
        'participation_fee_due_on',
        'created_by',
        'attendance_finalized_at',
        'attendance_finalized_by',
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
            'participation_fee_enabled' => 'boolean',
            'participation_fee_amount' => 'decimal:2',
            'participation_fee_due_on' => 'date',
            'attendance_finalized_at' => 'datetime',
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
            ->withPivot(['role', 'status', 'hours', 'notes', 'minutes_late', 'excuse_reason', 'checked_at', 'recorded_by'])
            ->withTimestamps();
    }

    public function participantRecords(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class);
    }

    public function attendanceFinalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_finalized_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function memberExpenses(): HasMany
    {
        return $this->hasMany(MemberExpense::class);
    }

    public function participationContributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Solde disponible sur l'activité.
     */
    public function remainingBudget(): float
    {
        return (float) $this->budget_allocated - (float) $this->budget_spent;
    }
}
