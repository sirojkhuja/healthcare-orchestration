<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class RejectTreatmentPlanCommand
{
    public function __construct(
        public string $planId,
        public string $reason,
    ) {}
}
