<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\MakeAppointmentRecurringCommand;
use App\Modules\Scheduling\Application\Data\AppointmentRecurrenceMaterializationData;
use App\Modules\Scheduling\Application\Services\AppointmentRecurrenceService;

final class MakeAppointmentRecurringCommandHandler
{
    public function __construct(
        private readonly AppointmentRecurrenceService $appointmentRecurrenceService,
    ) {}

    public function handle(MakeAppointmentRecurringCommand $command): AppointmentRecurrenceMaterializationData
    {
        return $this->appointmentRecurrenceService->create($command->appointmentId, $command->attributes);
    }
}
