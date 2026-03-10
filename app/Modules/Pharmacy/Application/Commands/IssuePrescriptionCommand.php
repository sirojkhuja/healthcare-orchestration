<?php

namespace App\Modules\Pharmacy\Application\Commands;

final readonly class IssuePrescriptionCommand
{
    public function __construct(
        public string $prescriptionId,
    ) {}
}
