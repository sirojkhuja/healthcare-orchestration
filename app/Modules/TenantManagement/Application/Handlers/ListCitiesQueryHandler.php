<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Queries\ListCitiesQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class ListCitiesQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\TenantManagement\Application\Data\LocationCityData>
     */
    public function handle(ListCitiesQuery $query): array
    {
        return $this->clinicAdministrationService->listCities($query->query);
    }
}
