<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class RemoveDiagnosisCommand
{
    public function __construct(
        public string $encounterId,
        public string $diagnosisId,
    ) {}
}
