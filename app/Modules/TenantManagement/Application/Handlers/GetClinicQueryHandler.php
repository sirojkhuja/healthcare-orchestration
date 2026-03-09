<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\ClinicData;
use App\Modules\TenantManagement\Application\Queries\GetClinicQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class GetClinicQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(GetClinicQuery $query): ClinicData
    {
        return $this->clinicAdministrationService->getClinic($query->clinicId);
    }
}
