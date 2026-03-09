<?php

namespace App\Modules\Patient\Application\Commands;

final readonly class DeletePatientCommand
{
    public function __construct(
        public string $patientId,
    ) {}
}
