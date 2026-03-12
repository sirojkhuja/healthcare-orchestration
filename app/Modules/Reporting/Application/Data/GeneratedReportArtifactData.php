<?php

namespace App\Modules\Reporting\Application\Data;

use Carbon\CarbonImmutable;

final readonly class GeneratedReportArtifactData
{
    public function __construct(
        public string $fileName,
        public string $contents,
        public int $rowCount,
        public CarbonImmutable $generatedAt,
    ) {}
}
