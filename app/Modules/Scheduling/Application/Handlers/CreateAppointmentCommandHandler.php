<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\CreateAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentAdministrationService;

final class CreateAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentAdministrationService $appointmentAdministrationService,
    ) {}

    public function handle(CreateAppointmentCommand $command): AppointmentData
    {
        return $this->appointmentAdministrationService->create($command->attributes);
    }
}
