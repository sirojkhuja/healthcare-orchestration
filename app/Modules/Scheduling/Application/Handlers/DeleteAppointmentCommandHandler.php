<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\DeleteAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentAdministrationService;

final class DeleteAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentAdministrationService $appointmentAdministrationService,
    ) {}

    public function handle(DeleteAppointmentCommand $command): AppointmentData
    {
        return $this->appointmentAdministrationService->delete($command->appointmentId);
    }
}
