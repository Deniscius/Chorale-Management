<?php

namespace App\Models;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Services\CommissionAccess;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class CommissionTeam extends Model
{
    use BelongsToTenant;

    protected $fillable = ['commission', 'leader_id', 'associate_id'];

    protected function casts(): array
    {
        return ['commission' => Commission::class, 'leader_id' => 'integer', 'associate_id' => 'integer'];
    }

    public function allows(?User $user): bool
    {
        if (! $user || (int) $this->tenant_id !== (int) $user->tenant_id) {
            return false;
        }
        if (in_array($this->commission->value, app(CommissionAccess::class)->inactiveArchiveCodes(), true)) {
            return false;
        }
        $leaderRole = $this->commission === Commission::Liturgique ? UserRole::CommissionLiturgique : UserRole::Secretaire;

        return ($user->id === $this->leader_id && ($user->role === $leaderRole || in_array($this->commission->value, app(CommissionAccess::class)->archiveCodes($user), true))) || $user->id === $this->associate_id;
    }

    public function scopeAllowing($query, User $user)
    {
        $codes = app(CommissionAccess::class)->archiveCodes($user);
        if ($user->role === UserRole::CommissionLiturgique) {
            $codes[] = Commission::Liturgique->value;
        }
        if ($user->role === UserRole::Secretaire) {
            $codes[] = Commission::Secretariat->value;
        }

        return $query->whereNotIn('commission', app(CommissionAccess::class)->inactiveArchiveCodes())
            ->where(fn ($q) => $q->where('associate_id', $user->id)->orWhere(fn ($q) => $q->where('leader_id', $user->id)->whereIn('commission', $codes)));
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function associate()
    {
        return $this->belongsTo(User::class, 'associate_id');
    }
}
