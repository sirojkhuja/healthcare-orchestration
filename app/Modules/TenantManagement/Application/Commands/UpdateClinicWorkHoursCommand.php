<?php

namespace App\Modules\TenantManagement\Application\Commands;

use App\Modules\TenantManagement\Application\Data\ClinicWorkHoursData;

final readonly class UpdateClinicWorkHoursCommand
{
    public function __construct(
        public string $clinicId,
        public ClinicWorkHoursData $workHours,
    ) {}
}
