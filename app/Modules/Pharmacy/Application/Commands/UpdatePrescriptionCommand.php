<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class UpdatePrescriptionCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $prescriptionId,
        public array $attributes,
    ) {}
}
