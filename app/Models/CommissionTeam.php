<?php

namespace App\Models;

use App\Enums\Commission;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;

final class CommissionTeam extends Model
{
    protected $fillable = ['commission', 'leader_id', 'associate_id'];

    protected function casts(): array
    {
        return ['commission' => Commission::class, 'leader_id' => 'integer', 'associate_id' => 'integer'];
    }

    public function allows(?User $user): bool
    {
        if (! $user) {
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
