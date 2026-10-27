<?php

namespace App\Models;

use App\Enums\Commission;
use App\Services\PrivateFiles;
use App\Tenancy\BelongsToTenant;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document archivé pour une commission.
 *
 * Le fichier reste privé ; la route de consultation applique son niveau
 * de visibilité et les droits de la commission dans la même chorale.
 */
class Document extends Model
{
    use BelongsToTenant;

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
        'visibility', 'document_date', 'correspondence_id',
    ];

    protected function casts(): array
    {
        return [
            'commission' => Commission::class,
            'size' => 'integer',
            'document_date' => 'date',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(CommissionTeam::class, 'commission', 'commission');
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function canManage(?User $user): bool
    {
        return $user !== null && (int) $this->tenant_id === (int) $user->tenant_id
            && $this->commission->canManageArchive($user, $this->team);
    }

    public function canView(?User $user): bool
    {
        if (! $user || (int) $this->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        return match ($this->visibility) {
            'published' => true,
            'commission' => $this->commission->canView($user) || $this->canManage($user),
            'private' => $this->canManage($user),
            default => false,
        };
    }

    public function scopeReadable(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1=0');
        }
        $readable = array_map(fn ($c) => $c->value, Commission::forUser($user));
        $manageable = array_map(fn ($c) => $c->value, array_filter(Commission::cases(),
            fn ($c) => ! $c->hasPrivateArchive() && $c->canManageArchive($user)));

        return $query->where($query->qualifyColumn('tenant_id'), $user->tenant_id)->where(function (Builder $q) use ($user, $readable, $manageable) {
            $q->where('visibility', 'published')
                ->orWhere(fn ($q) => $q->where('visibility', 'commission')->whereIn('commission', $readable))
                ->orWhere(function ($q) use ($user, $manageable) {
                    $q->whereIn('visibility', ['private', 'commission'])->where(function ($q) use ($user, $manageable) {
                        $q->whereIn('commission', $manageable)->orWhere(fn ($q) => $q
                            ->whereIn('commission', [Commission::Liturgique->value, Commission::Secretariat->value])
                            ->whereHas('team', fn ($q) => $q->allowing($user)));
                    });
                });
        });
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
        return app(PrivateFiles::class)->exists($this->path);
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
