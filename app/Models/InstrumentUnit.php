<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InstrumentUnit extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id', 'inventory_code', 'instrument_id', 'ordinal'];

    protected function casts(): array
    {
        return ['retired_at' => 'datetime', 'ordinal' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $unit) {
            abort_if($unit->isDirty(['inventory_code', 'instrument_id', 'ordinal']), 403, 'Un identifiant attribué ne peut pas être modifié.');
        });
        static::deleting(fn () => abort(403, 'Retirez l’article de l’inventaire sans supprimer son identifiant.'));
    }

    public function instrument()
    {
        return $this->belongsTo(Instrument::class);
    }
}
