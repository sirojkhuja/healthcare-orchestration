<?php

namespace App\Modules\Patient\Application\Queries;

final readonly class ListPatientTagsQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
