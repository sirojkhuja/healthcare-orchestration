<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class ResumeTreatmentPlanCommand
{
    public function __construct(
        public string $planId,
    ) {}
}
