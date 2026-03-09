<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class DetachPatientExternalRefCommand
{
    public function __construct(
        public string $patientId,
        public string $referenceId,
    ) {}
}
