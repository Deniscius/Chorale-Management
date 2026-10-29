<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Services\CommissionAccess;
use App\Tenancy\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use BelongsToTenant;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private ?int $approvedInitialMemberId = null;

    /** Only the signed-in owner can attach a newly created, same-tenant personal record. */
    public function attachNewPersonalMember(Member $member): void
    {
        abort_unless(auth()->check() && $this->exists && auth()->id() === $this->id
            && array_key_exists('member_id', $this->getRawOriginal()) && ! $this->member_id
            && $member->exists && $member->wasRecentlyCreated
            && (int) $member->tenant_id === (int) $this->tenant_id, 403);
        $this->approvedInitialMemberId = $member->id;
        try {
            $this->member_id = $member->id;
            $this->save();
        } finally {
            $this->approvedInitialMemberId = null;
        }
    }

    public function isCompletingOwnMemberProfile(): bool
    {
        return auth()->id() === $this->id && $this->getRawOriginal('member_id') === null
            && $this->approvedInitialMemberId !== null
            && (int) $this->member_id === $this->approvedInitialMemberId;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'member_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /**
     * Le choriste rattaché à ce compte, le cas échéant.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Propose un identifiant à partir d'une adresse e-mail (partie avant « @ »).
     */
    public static function suggestUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($email, '@', true) ?: '')) ?? '';

        return $base !== '' ? $base : 'utilisateur';
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * L'utilisateur peut-il accéder au module fonctionnel donné ?
     */
    public function accessibleModules(): array
    {
        if (in_array($this->role, [UserRole::Admin, UserRole::Directeur], true)) {
            return $this->role->modules();
        }

        return array_values(array_unique([...$this->role->modules(), ...app(CommissionAccess::class)->modules($this)]));
    }

    public function canAccessModule(string $module): bool
    {
        return $this->role->canAccessModule($module) || in_array($module, app(CommissionAccess::class)->modules($this), true);
    }

    /**
     * L'utilisateur a-t-il le droit d'écrire dans ce module ?
     */
    public function canManageModule(string $module): bool
    {
        return $this->role->canManageModule($module) || in_array($module, app(CommissionAccess::class)->modules($this, true), true);
    }
}
