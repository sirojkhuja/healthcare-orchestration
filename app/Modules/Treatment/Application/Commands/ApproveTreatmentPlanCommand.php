<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class ApproveTreatmentPlanCommand
{
    public function __construct(
        public string $planId,
    ) {}
}
