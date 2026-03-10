<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\ScheduleAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class ScheduleAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(ScheduleAppointmentCommand $command): AppointmentData
    {
        return $this->appointmentWorkflowService->schedule($command->appointmentId);
    }
}
