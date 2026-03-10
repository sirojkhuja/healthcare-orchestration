<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class DeleteMedicationCommand
{
    public function __construct(
        public string $medicationId,
    ) {}
}
