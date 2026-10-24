<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\ChoirFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Choir extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ChoirFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parish',
        'patron',
        'target_size',
        'founded_on',
        'director_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'founded_on' => 'date',
            'target_size' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'director_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function works(): HasMany
    {
        return $this->hasMany(Work::class);
    }

    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    public function rehearsals(): HasMany
    {
        return $this->hasMany(Rehearsal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class);
    }

    public function instrumentEnsembles(): HasMany
    {
        return $this->hasMany(InstrumentEnsemble::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /**
     * Effectif actif de la chorale.
     */
    public function activeMembersCount(): int
    {
        return $this->members()->where('status', 'actif')->count();
    }
}
