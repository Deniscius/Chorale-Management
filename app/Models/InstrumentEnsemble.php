<?php

namespace App\Models;

use Database\Factories\InstrumentEnsembleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentEnsemble extends Model
{
    /** @use HasFactory<InstrumentEnsembleFactory> */
    use HasFactory;

    protected $fillable = [
        'choir_id',
        'name',
        'description',
        'conductor_id',
        'member_count',
    ];

    protected function casts(): array
    {
        return [
            'member_count' => 'integer',
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'conductor_id');
    }
}
