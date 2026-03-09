<?php

namespace App\Modules\Insurance\Application\Queries;

final readonly class ListPatientInsuranceQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
