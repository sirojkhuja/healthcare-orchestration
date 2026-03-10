<?php

namespace App\Modules\Pharmacy\Application\Queries;

final readonly class GetMedicationQuery
{
    public function __construct(
        public string $medicationId,
    ) {}
}
