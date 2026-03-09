<?php

namespace App\Modules\Integrations\Application\Queries;

final readonly class ListPatientExternalRefsQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
