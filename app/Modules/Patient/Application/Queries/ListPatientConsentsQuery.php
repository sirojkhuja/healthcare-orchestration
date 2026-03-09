<?php

namespace App\Modules\Patient\Application\Queries;

final readonly class ListPatientConsentsQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
