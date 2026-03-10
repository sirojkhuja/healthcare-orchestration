<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class UpdateTreatmentItemCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $planId,
        public string $itemId,
        public array $attributes,
    ) {}
}
