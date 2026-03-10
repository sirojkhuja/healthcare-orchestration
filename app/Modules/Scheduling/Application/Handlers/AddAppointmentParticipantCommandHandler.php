<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\AddAppointmentParticipantCommand;
use App\Modules\Scheduling\Application\Data\AppointmentParticipantData;
use App\Modules\Scheduling\Application\Services\AppointmentParticipantService;

final class AddAppointmentParticipantCommandHandler
{
    public function __construct(
        private readonly AppointmentParticipantService $appointmentParticipantService,
    ) {}

    public function handle(AddAppointmentParticipantCommand $command): AppointmentParticipantData
    {
        return $this->appointmentParticipantService->create($command->appointmentId, $command->attributes);
    }
}
