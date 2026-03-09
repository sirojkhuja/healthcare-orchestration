<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\ClinicWorkHoursData;
use App\Modules\TenantManagement\Application\Queries\GetClinicWorkHoursQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class GetClinicWorkHoursQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(GetClinicWorkHoursQuery $query): ClinicWorkHoursData
    {
        return $this->clinicAdministrationService->getClinicWorkHours($query->clinicId);
    }
}
