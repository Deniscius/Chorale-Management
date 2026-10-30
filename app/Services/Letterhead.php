<?php

namespace App\Services;

use App\Models\LetterheadTemplate;

final class Letterhead
{
    public function __construct(private readonly PrivateFiles $files) {}

    public function active(): ?LetterheadTemplate
    {
        return LetterheadTemplate::query()->where('status', LetterheadTemplate::STATUS_ACTIVE)->latest('activated_at')->first();
    }

    public function headerDataUri(): ?string
    {
        $path = $this->active()?->header_path;

        return $path ? $this->files->imageDataUri($path, 5 * 1024 * 1024) : null;
    }

    public function headerHeightMm(): int
    {
        return max(20, min(100, (int) ($this->active()?->header_height_mm ?? 45)));
    }
}
