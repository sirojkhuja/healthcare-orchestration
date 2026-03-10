<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class DeleteTreatmentPlanCommand
{
    public function __construct(
        public string $planId,
    ) {}
}
