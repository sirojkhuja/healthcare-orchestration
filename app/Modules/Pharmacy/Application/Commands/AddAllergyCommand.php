<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class AddAllergyCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $patientId,
        public array $attributes,
    ) {}
}
