<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\UpdateClinicWorkHoursCommand;
use App\Modules\TenantManagement\Application\Data\ClinicWorkHoursData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class UpdateClinicWorkHoursCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(UpdateClinicWorkHoursCommand $command): ClinicWorkHoursData
    {
        return $this->clinicAdministrationService->updateClinicWorkHours($command->clinicId, $command->workHours);
    }
}
