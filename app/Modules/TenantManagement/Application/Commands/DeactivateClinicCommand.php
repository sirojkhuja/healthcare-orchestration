<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class DeactivateClinicCommand
{
    public function __construct(
        public string $clinicId,
    ) {}
}
