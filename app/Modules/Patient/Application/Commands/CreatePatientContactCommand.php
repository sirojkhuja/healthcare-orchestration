<?php

namespace App\Modules\Patient\Application\Commands;

final readonly class CreatePatientContactCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $patientId,
        public array $attributes,
    ) {}
}
