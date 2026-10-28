<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LetterheadTemplate extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REPLACED = 'replaced';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'original_name', 'original_path', 'preview_path', 'header_path', 'status',
        'header_height_mm', 'error_message', 'activated_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['activated_at' => 'immutable_datetime'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_ACTIVE, self::STATUS_REPLACED], true)
            && filled($this->header_path);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'En attente', self::STATUS_PROCESSING => 'En traitement',
            self::STATUS_READY => 'À vérifier', self::STATUS_ACTIVE => 'Actif',
            self::STATUS_REPLACED => 'Remplacé', self::STATUS_ERROR => 'Erreur',
        ];
    }
}
