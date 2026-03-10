<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\CompleteAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class CompleteAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(CompleteAppointmentCommand $command): AppointmentData
    {
        return $this->appointmentWorkflowService->complete($command->appointmentId);
    }
}
