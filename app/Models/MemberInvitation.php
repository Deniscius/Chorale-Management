<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class MemberInvitation extends Model
{
    use BelongsToTenant;

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['role' => UserRole::class, 'expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
