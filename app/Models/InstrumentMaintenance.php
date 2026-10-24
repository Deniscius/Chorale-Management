<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Tenancy\BelongsToTenant;
use Database\Factories\InstrumentMaintenanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentMaintenance extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<InstrumentMaintenanceFactory> */
    use HasFactory;

    protected $fillable = [
        'instrument_id',
        'type',
        'description',
        'provider',
        'cost',
        'performed_on',
        'scheduled_for',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'status' => MaintenanceStatus::class,
            'cost' => 'decimal:2',
            'performed_on' => 'date',
            'scheduled_for' => 'date',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
