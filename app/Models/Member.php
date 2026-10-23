<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Enums\Voice;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'choir_id',
        'first_name',
        'last_name',
        'sex',
        'birth_date',
        'voice',
        'part',
        'email',
        'notify_email',
        'phone',
        'notify_sms',
        'address',
        'profession',
        'commission',
        'joined_on',
        'left_on',
        'status',
        'notes',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'joined_on' => 'date',
            'left_on' => 'date',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'voice' => Voice::class,
            'status' => MemberStatus::class,
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function rehearsalAttendances(): HasMany
    {
        return $this->hasMany(RehearsalAttendance::class);
    }

    public function activityParticipants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * Le choriste peut-il recevoir un rappel par e-mail ?
     */
    public function canReceiveEmail(): bool
    {
        return $this->notify_email && filled($this->email);
    }

    /**
     * Le choriste peut-il recevoir un rappel par SMS ?
     *
     * Le canal SMS doit en plus être activé au niveau de l'application.
     */
    public function canReceiveSms(): bool
    {
        return $this->notify_sms
            && filled($this->phone)
            && config('chorale.reminders.sms_enabled');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /**
     * Le choriste dispose-t-il d'un compte d'accès à l'application ?
     */
    public function hasAccount(): bool
    {
        return $this->user !== null;
    }

    /**
     * Rôle applicatif du choriste, s'il dispose d'un compte.
     */
    public function accessRole(): ?UserRole
    {
        return $this->user?->role;
    }

    /**
     * Scope : choristes actifs.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MemberStatus::Actif->value);
    }

    /**
     * Scope : recherche plein texte simple.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}
