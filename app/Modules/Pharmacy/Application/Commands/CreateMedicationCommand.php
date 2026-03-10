<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class CreateMedicationCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
