<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Data\ClinicSettingsData;
use App\Modules\TenantManagement\Application\Queries\GetClinicSettingsQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class GetClinicSettingsQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(GetClinicSettingsQuery $query): ClinicSettingsData
    {
        return $this->clinicAdministrationService->getClinicSettings($query->clinicId);
    }
}
