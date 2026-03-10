<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class UpdateTreatmentPlanCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $planId,
        public array $attributes,
    ) {}
}
