<?php

namespace App\Modules\Patient\Application\Queries;

final readonly class ListPatientDocumentsQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
