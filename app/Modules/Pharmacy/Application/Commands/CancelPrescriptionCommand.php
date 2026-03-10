<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class CancelPrescriptionCommand
{
    public function __construct(
        public string $prescriptionId,
        public string $reason,
    ) {}
}
