<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Queries\SearchLocationsQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class SearchLocationsQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\TenantManagement\Application\Data\LocationSearchResultData>
     */
    public function handle(SearchLocationsQuery $query): array
    {
        return $this->clinicAdministrationService->searchLocations($query->query);
    }
}
