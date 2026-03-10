<?php

namespace App\Modules\Treatment\Application\Queries;

final readonly class GetTreatmentPlanQuery
{
    public function __construct(
        public string $planId,
    ) {}
}
