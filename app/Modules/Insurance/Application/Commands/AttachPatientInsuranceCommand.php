<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class AttachPatientInsuranceCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $patientId,
        public array $attributes,
    ) {}
}
