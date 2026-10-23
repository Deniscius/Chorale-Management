<?php

namespace App\Models;

use App\Enums\Commission;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Document archivé pour une commission.
 *
 * Le fichier est stocké sur le disque privé et n'est consultable qu'à
 * travers une route protégée par les droits du module correspondant.
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'choir_id',
        'commission',
        'title',
        'description',
        'category',
        'original_name',
        'path',
        'mime_type',
        'size',
        'member_id',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'commission' => Commission::class,
            'size' => 'integer',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Taille lisible (Ko / Mo).
     */
    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', ' ').' Mo';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', ' ').' Ko';
        }

        return $bytes.' o';
    }

    /**
     * Le fichier physique est-il toujours présent ?
     */
    public function fileExists(): bool
    {
        return Storage::disk('local')->exists($this->path);
    }

    public function scopeForCommission(Builder $query, Commission|string $commission): Builder
    {
        $value = $commission instanceof Commission ? $commission->value : $commission;

        return $query->where('commission', $value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereLike('title', $like)
                ->orWhereLike('description', $like)
                ->orWhereLike('original_name', $like);
        });
    }
}
