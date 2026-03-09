<?php

namespace App\Modules\Patient\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientStoredDocumentData
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
