<?php

namespace App\Models;

use App\Enums\DonationType;
use App\Enums\DonorType;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'choir_id',
        'donor_type',
        'donation_type',
        'donor_name',
        'member_id',
        'amount',
        'donated_on',
        'purpose',
        'transaction_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'donor_type' => DonorType::class,
            'donation_type' => DonationType::class,
            'amount' => 'decimal:2',
            'donated_on' => 'date',
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
