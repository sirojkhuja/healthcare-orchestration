<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class DetachPatientInsuranceCommand
{
    public function __construct(
        public string $patientId,
        public string $policyId,
    ) {}
}
