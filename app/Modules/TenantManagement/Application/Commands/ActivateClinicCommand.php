<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class ActivateClinicCommand
{
    public function __construct(
        public string $clinicId,
    ) {}
}
