<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\RestoreAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class RestoreAppointmentCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(RestoreAppointmentCommand $command): AppointmentData
    {
        return $this->appointmentWorkflowService->restore($command->appointmentId);
    }
}
