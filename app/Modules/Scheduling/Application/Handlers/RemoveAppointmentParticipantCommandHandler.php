<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\RemoveAppointmentParticipantCommand;
use App\Modules\Scheduling\Application\Data\AppointmentParticipantData;
use App\Modules\Scheduling\Application\Services\AppointmentParticipantService;

final class RemoveAppointmentParticipantCommandHandler
{
    public function __construct(
        private readonly AppointmentParticipantService $appointmentParticipantService,
    ) {}

    public function handle(RemoveAppointmentParticipantCommand $command): AppointmentParticipantData
    {
        return $this->appointmentParticipantService->delete($command->appointmentId, $command->participantId);
    }
}
