<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class CreateTreatmentPlanCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
