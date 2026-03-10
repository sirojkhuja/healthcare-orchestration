<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\BulkRescheduleAppointmentsCommand;
use App\Modules\Scheduling\Application\Data\BulkAppointmentTransitionData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class BulkRescheduleAppointmentsCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(BulkRescheduleAppointmentsCommand $command): BulkAppointmentTransitionData
    {
        return $this->appointmentWorkflowService->bulkReschedule($command->items);
    }
}
