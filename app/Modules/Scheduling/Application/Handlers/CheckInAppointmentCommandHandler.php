<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\CheckInAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class CheckInAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(CheckInAppointmentCommand $command): AppointmentData
    {
        return $this->appointmentWorkflowService->checkIn($command->appointmentId, $command->adminOverride);
    }
}
