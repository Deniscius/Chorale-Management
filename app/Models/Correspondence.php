<?php

namespace App\Models;

use App\Enums\Confidentiality;
use App\Enums\CorrespondenceChannel;
use App\Enums\CorrespondenceStatus;
use App\Enums\CorrespondenceType;
use App\Services\CorrespondenceReferenceGenerator;
use Database\Factories\CorrespondenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Correspondence extends Model
{
    /** @use HasFactory<CorrespondenceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'type',
        'correspondence_date',
        'received_at',
        'sent_at',
        'sender_name',
        'sender_organization',
        'recipient_name',
        'service_id',
        'subject',
        'body',
        'channel',
        'confidentiality',
        'status',
        'assigned_to',
        'document_path',
        'processed_at',
        'closed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CorrespondenceType::class,
            'status' => CorrespondenceStatus::class,
            'channel' => CorrespondenceChannel::class,
            'confidentiality' => Confidentiality::class,
            'correspondence_date' => 'date',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
            'processed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Le chrono (CE/2026/0001) est attribué automatiquement à l'enregistrement.
     */
    protected static function booted(): void
    {
        static::creating(function (self $correspondence) {
            if (blank($correspondence->reference)) {
                $correspondence->reference = app(CorrespondenceReferenceGenerator::class)->next(
                    $correspondence->type,
                    $correspondence->correspondence_date?->year,
                );
            }
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceService::class, 'service_id');
    }

    public function assignedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_to');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CorrespondenceEvent::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', CorrespondenceStatus::open());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('reference', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('sender_name', 'like', $like)
                ->orWhere('sender_organization', 'like', $like)
                ->orWhere('recipient_name', 'like', $like);
        });
    }

    public function isOpen(): bool
    {
        return in_array($this->status, CorrespondenceStatus::open(), true);
    }
}
