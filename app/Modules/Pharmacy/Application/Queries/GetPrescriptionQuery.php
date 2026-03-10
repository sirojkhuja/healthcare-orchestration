<?php

namespace App\Modules\Pharmacy\Application\Queries;

final readonly class GetPrescriptionQuery
{
    public function __construct(
        public string $prescriptionId,
    ) {}
}
