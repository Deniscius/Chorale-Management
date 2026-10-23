<?php

namespace App\Models;

use Database\Factories\LiturgicalSeasonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiturgicalSeason extends Model
{
    /** @use HasFactory<LiturgicalSeasonFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'starts_on',
        'ends_on',
        'color',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function works(): HasMany
    {
        return $this->hasMany(Work::class, 'season_id');
    }
}
