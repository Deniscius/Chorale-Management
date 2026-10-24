<?php

namespace App\Models\Platform;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class Admin extends Authenticatable
{
    protected $table = 'platform_admins';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'totp_secret', 'last_totp_counter'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'totp_secret' => 'encrypted', 'enabled' => 'boolean', 'last_login_at' => 'immutable_datetime'];
    }
}
