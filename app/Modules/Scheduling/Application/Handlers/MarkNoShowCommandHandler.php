<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\MarkNoShowCommand;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class MarkNoShowCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(MarkNoShowCommand $command): AppointmentData
    {
        return $this->appointmentWorkflowService->markNoShow($command->appointmentId, $command->reason);
    }
}
