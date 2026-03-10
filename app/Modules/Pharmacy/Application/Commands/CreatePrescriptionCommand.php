<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class CreatePrescriptionCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
