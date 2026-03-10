<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\BulkCancelAppointmentsCommand;
use App\Modules\Scheduling\Application\Data\BulkAppointmentTransitionData;
use App\Modules\Scheduling\Application\Services\AppointmentWorkflowService;

final class BulkCancelAppointmentsCommandHandler
{
    public function __construct(
        private readonly AppointmentWorkflowService $appointmentWorkflowService,
    ) {}

    public function handle(BulkCancelAppointmentsCommand $command): BulkAppointmentTransitionData
    {
        return $this->appointmentWorkflowService->bulkCancel($command->appointmentIds, $command->reason);
    }
}
