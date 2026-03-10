<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class RemoveAllergyCommand
{
    public function __construct(
        public string $patientId,
        public string $allergyId,
    ) {}
}
