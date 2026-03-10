<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\StartAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class StartAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(StartAppointmentCommand $command): AppointmentData
    {
        return $this->appointmentWorkflowService->start($command->appointmentId);
    }
}
