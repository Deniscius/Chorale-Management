<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\WorkType;
use App\Tenancy\BelongsToTenant;
use Database\Factories\WorkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Work extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WorkFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'choir_id',
        'season_id',
        'title',
        'type',
        'composer',
        'arranger',
        'text_author',
        'language',
        'key_signature',
        'mode',
        'tempo',
        'duration_minutes',
        'difficulty',
        'min_voices',
        'required_voices',
        'instrumentation',
        'is_repertoire',
        'pdf_path',
        'midi_path',
        'lyrics_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkType::class,
            'difficulty' => Difficulty::class,
            'required_voices' => 'array',
            'instrumentation' => 'array',
            'is_repertoire' => 'boolean',
            'tempo' => 'integer',
            'duration_minutes' => 'integer',
            'min_voices' => 'integer',
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(LiturgicalSeason::class);
    }

    public function performances(): BelongsToMany
    {
        return $this->belongsToMany(Performance::class)
            ->withPivot(['order', 'role', 'choir_part', 'duration_minutes', 'notes'])
            ->withTimestamps()
            ->orderBy('performance_work.order');
    }

    public function rehearsals(): BelongsToMany
    {
        return $this->belongsToMany(Rehearsal::class)
            ->withPivot(['order', 'key_signature', 'tempo', 'focus', 'notes'])
            ->withTimestamps()
            ->orderBy('rehearsal_work.order');
    }

    public function hasPartition(): bool
    {
        return filled($this->pdf_path);
    }
}
