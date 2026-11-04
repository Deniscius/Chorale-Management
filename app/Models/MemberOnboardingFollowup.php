<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class MemberOnboardingFollowup extends Model
{
    use BelongsToTenant;

    protected $fillable = ['member_id', 'status', 'contacted_on', 'note', 'updated_by'];

    protected function casts(): array
    {
        return ['contacted_on' => 'date'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
