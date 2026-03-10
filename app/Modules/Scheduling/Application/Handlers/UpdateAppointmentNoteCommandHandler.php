<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\UpdateAppointmentNoteCommand;
use App\Modules\Scheduling\Application\Data\AppointmentNoteData;
use App\Modules\Scheduling\Application\Services\AppointmentNoteService;

final class UpdateAppointmentNoteCommandHandler
{
    public function __construct(
        private readonly AppointmentNoteService $appointmentNoteService,
    ) {}

    public function handle(UpdateAppointmentNoteCommand $command): AppointmentNoteData
    {
        return $this->appointmentNoteService->update($command->appointmentId, $command->noteId, $command->attributes);
    }
}
