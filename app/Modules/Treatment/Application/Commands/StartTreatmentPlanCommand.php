<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class StartTreatmentPlanCommand
{
    public function __construct(
        public string $planId,
    ) {}
}
