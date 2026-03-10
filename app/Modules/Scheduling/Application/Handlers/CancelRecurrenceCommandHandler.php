<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\CancelRecurrenceCommand;
use App\Modules\Scheduling\Application\Data\AppointmentRecurrenceData;
use App\Modules\Scheduling\Application\Services\AppointmentRecurrenceService;

final class CancelRecurrenceCommandHandler
{
    public function __construct(
        private readonly AppointmentRecurrenceService $appointmentRecurrenceService,
    ) {}

    public function handle(CancelRecurrenceCommand $command): AppointmentRecurrenceData
    {
        return $this->appointmentRecurrenceService->cancel($command->recurrenceId, $command->reason);
    }
}
