<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class DeleteClinicCommand
{
    public function __construct(
        public string $clinicId,
    ) {}
}
