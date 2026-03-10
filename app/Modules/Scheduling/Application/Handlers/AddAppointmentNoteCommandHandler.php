<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\AddAppointmentNoteCommand;
use App\Modules\Scheduling\Application\Data\AppointmentNoteData;
use App\Modules\Scheduling\Application\Services\AppointmentNoteService;

final class AddAppointmentNoteCommandHandler
{
    public function __construct(
        private readonly AppointmentNoteService $appointmentNoteService,
    ) {}

    public function handle(AddAppointmentNoteCommand $command): AppointmentNoteData
    {
        return $this->appointmentNoteService->create($command->appointmentId, $command->attributes);
    }
}
