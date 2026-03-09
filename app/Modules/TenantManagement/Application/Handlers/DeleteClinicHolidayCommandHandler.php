<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\DeleteClinicHolidayCommand;
use App\Modules\TenantManagement\Application\Data\ClinicHolidayData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class DeleteClinicHolidayCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(DeleteClinicHolidayCommand $command): ClinicHolidayData
    {
        return $this->clinicAdministrationService->deleteHoliday($command->clinicId, $command->holidayId);
    }
}
