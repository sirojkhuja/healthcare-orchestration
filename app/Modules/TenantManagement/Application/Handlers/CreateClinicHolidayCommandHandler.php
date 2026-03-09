<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\CreateClinicHolidayCommand;
use App\Modules\TenantManagement\Application\Data\ClinicHolidayData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class CreateClinicHolidayCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(CreateClinicHolidayCommand $command): ClinicHolidayData
    {
        return $this->clinicAdministrationService->createHoliday($command->clinicId, $command->attributes);
    }
}
