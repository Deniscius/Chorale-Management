<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\RehearsalType;
use App\Tenancy\BelongsToTenant;
use Database\Factories\RehearsalFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rehearsal extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<RehearsalFactory> */
    use HasFactory;

    protected $fillable = [
        'choir_id',
        'date',
        'starts_at',
        'ends_at',
        'venue',
        'type',
        'status',
        'notes',
        'ai_report',
        'ai_report_generated_at',
        'created_by',
        'attendance_finalized_at',
        'attendance_finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => RehearsalType::class,
            'status' => EventStatus::class,
            'ai_report_generated_at' => 'datetime',
            'attendance_finalized_at' => 'datetime',
        ];
    }

    /**
     * Heure de début au format HH:MM.
     */
    protected function startsAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => filled($value) ? substr($value, 0, 5) : null,
            set: fn (?string $value) => filled($value) ? $this->normalizeTime($value) : null,
        );
    }

    /**
     * Heure de fin au format HH:MM.
     */
    protected function endsAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => filled($value) ? substr($value, 0, 5) : null,
            set: fn (?string $value) => filled($value) ? $this->normalizeTime($value) : null,
        );
    }

    /**
     * Normalise une heure saisie (HH:MM ou HH:MM:SS) vers HH:MM:SS.
     */
    private function normalizeTime(string $value): string
    {
        $value = trim($value);

        return strlen($value) === 5 ? $value.':00' : $value;
    }

    /**
     * Créneau horaire de la répétition.
     */
    public function timeRange(): ?string
    {
        if (blank($this->starts_at)) {
            return null;
        }

        return blank($this->ends_at)
            ? $this->starts_at
            : $this->starts_at.' – '.$this->ends_at;
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
            ->withPivot(['order', 'key_signature', 'tempo', 'focus', 'notes'])
            ->withTimestamps()
            ->orderBy('rehearsal_work.order');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(RehearsalAttendance::class);
    }

    public function attendanceFinalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_finalized_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function presentCount(): int
    {
        return $this->attendances()->where('status', 'present')->count();
    }

    public function absentCount(): int
    {
        return $this->attendances()->where('status', 'absent')->count();
    }
}
