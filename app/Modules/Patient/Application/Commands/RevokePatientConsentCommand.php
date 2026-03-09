<?php

namespace App\Modules\Patient\Application\Commands;

final readonly class RevokePatientConsentCommand
{
    public function __construct(
        public string $patientId,
        public string $consentId,
        public ?string $reason,
    ) {}
}
