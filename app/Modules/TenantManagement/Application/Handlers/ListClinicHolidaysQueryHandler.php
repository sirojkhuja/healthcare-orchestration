<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Queries\ListClinicHolidaysQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class ListClinicHolidaysQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\TenantManagement\Application\Data\ClinicHolidayData>
     */
    public function handle(ListClinicHolidaysQuery $query): array
    {
        return $this->clinicAdministrationService->listHolidays($query->clinicId);
    }
}
