<?php

namespace App\Modules\Patient\Application\Commands;

final readonly class DeletePatientContactCommand
{
    public function __construct(
        public string $patientId,
        public string $contactId,
    ) {}
}
