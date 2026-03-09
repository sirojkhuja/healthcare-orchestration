<?php

namespace App\Modules\Patient\Application\Queries;

final readonly class GetPatientTimelineQuery
{
    public function __construct(
        public string $patientId,
        public int $limit = 50,
    ) {}
}
