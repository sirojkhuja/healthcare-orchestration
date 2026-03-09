<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Queries\ListDistrictsQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class ListDistrictsQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\TenantManagement\Application\Data\LocationDistrictData>
     */
    public function handle(ListDistrictsQuery $query): array
    {
        return $this->clinicAdministrationService->listDistricts($query->cityCode);
    }
}
