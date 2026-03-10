<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\RescheduleAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentRescheduleData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class RescheduleAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(RescheduleAppointmentCommand $command): AppointmentRescheduleData
    {
        return $this->appointmentWorkflowService->reschedule($command->appointmentId, $command->attributes);
    }
}
