<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class UpdateMedicationCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $medicationId,
        public array $attributes,
    ) {}
}
