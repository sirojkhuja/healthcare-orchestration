<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\UpdateAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentAdministrationService;

final class UpdateAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentAdministrationService $appointmentAdministrationService,
    ) {}

    public function handle(UpdateAppointmentCommand $command): AppointmentData
    {
        return $this->appointmentAdministrationService->update($command->appointmentId, $command->attributes);
    }
}
