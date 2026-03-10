<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class FinishTreatmentPlanCommand
{
    public function __construct(
        public string $planId,
    ) {}
}
