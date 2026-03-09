<?php

namespace App\Modules\Patient\Application\Queries;

final readonly class GetPatientSummaryQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
