<?php

namespace App\Modules\Pharmacy\Application\Queries;

final readonly class ListAllergiesQuery
{
    public function __construct(
        public string $patientId,
    ) {}
}
