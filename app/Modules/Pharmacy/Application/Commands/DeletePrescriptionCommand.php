<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class DeletePrescriptionCommand
{
    public function __construct(
        public string $prescriptionId,
    ) {}
}
