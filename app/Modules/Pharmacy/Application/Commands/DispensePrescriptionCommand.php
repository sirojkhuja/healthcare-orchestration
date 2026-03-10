<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class DispensePrescriptionCommand
{
    public function __construct(
        public string $prescriptionId,
    ) {}
}
