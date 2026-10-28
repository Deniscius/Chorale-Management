<?php

namespace App\Models;

use App\Enums\Voice;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VoiceResource extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'voice', 'title', 'lyrics', 'audio_path', 'audio_original_name',
        'audio_mime', 'audio_size', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'voice' => Voice::class,
            'audio_size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
