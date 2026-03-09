<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Queries\ListClinicsQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class ListClinicsQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\TenantManagement\Application\Data\ClinicData>
     */
    public function handle(ListClinicsQuery $query): array
    {
        return $this->clinicAdministrationService->listClinics($query->search, $query->status);
    }
}
