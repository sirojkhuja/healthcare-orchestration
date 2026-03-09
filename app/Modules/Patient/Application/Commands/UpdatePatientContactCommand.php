<?php

namespace App\Modules\Patient\Application\Commands;

final readonly class UpdatePatientContactCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $patientId,
        public string $contactId,
        public array $attributes,
    ) {}
}
