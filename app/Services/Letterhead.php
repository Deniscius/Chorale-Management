<?php

namespace App\Services;

use App\Models\LetterheadTemplate;
use App\Tenancy\TenantContext;

final class Letterhead
{
    private array $memo = [];

    public function __construct(private readonly PrivateFiles $files) {}

    public function active(): ?LetterheadTemplate
    {
        return LetterheadTemplate::query()->where('status', LetterheadTemplate::STATUS_ACTIVE)->latest('activated_at')->first();
    }

    public function headerDataUri(): ?string
    {
        return $this->viewData()['header'];
    }

    public function headerHeightMm(): int
    {
        return $this->viewData()['headerHeightMm'];
    }

    /** One coherent header snapshot, preserving its physical image proportions. */
    public function viewData(): array
    {
        $tenant = app(TenantContext::class)->requireId();
        if (isset($this->memo[$tenant])) {
            return $this->memo[$tenant];
        }
        $active = $this->active();
        $height = max(20, min(100, (int) ($active?->header_height_mm ?? 45)));
        $header = $active?->header_path ? $this->files->imageDataUri($active->header_path, 10 * 1024 * 1024) : null;
        $width = 210.0;
        if ($header) {
            $image = base64_decode(explode(',', $header, 2)[1], true);
            $size = $image ? @getimagesizefromstring($image) : false;
            if ($size && $size[1] > 0) {
                $width = round(max(1, min(420, $height * $size[0] / $size[1])), 2);
            }
        }

        return $this->memo[$tenant] = ['header' => $header, 'headerHeightMm' => $height, 'headerWidthMm' => $width];
    }
}
