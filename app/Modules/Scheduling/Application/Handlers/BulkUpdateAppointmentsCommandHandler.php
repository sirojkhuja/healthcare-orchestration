<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\BulkUpdateAppointmentsCommand;
use App\Modules\Scheduling\Application\Data\BulkAppointmentUpdateData;
use App\Modules\Scheduling\Application\Services\AppointmentBulkUpdateService;

final class BulkUpdateAppointmentsCommandHandler
{
    public function __construct(
        private readonly AppointmentBulkUpdateService $appointmentBulkUpdateService,
    ) {}

    public function handle(BulkUpdateAppointmentsCommand $command): BulkAppointmentUpdateData
    {
        return $this->appointmentBulkUpdateService->update($command->appointmentIds, $command->changes);
    }
}
