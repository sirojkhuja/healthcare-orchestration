<?php

namespace App\Modules\Patient\Application\Queries;

final readonly class ListPatientContactsQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
