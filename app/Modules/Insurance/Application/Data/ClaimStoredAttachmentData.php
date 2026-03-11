<?php

namespace App\Modules\Insurance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ClaimStoredAttachmentData
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
        public CarbonImmutable $uploadedAt,
    ) {}
}
