<?php

namespace App\Modules\Patient\Application\Queries;

final readonly class GetPatientQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
