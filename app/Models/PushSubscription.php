<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'user_agent', 'last_success_at', 'last_failure_at'];

    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted',
            'public_key' => 'encrypted',
            'auth_token' => 'encrypted',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
