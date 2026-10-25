<?php

namespace App\Models;

use App\Enums\Commission;
use App\Enums\UserRole;
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
        $leaderRole = $this->commission === Commission::Liturgique ? UserRole::CommissionLiturgique : UserRole::Secretaire;

        return ($user->id === $this->leader_id && $user->role === $leaderRole) || $user->id === $this->associate_id;
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
