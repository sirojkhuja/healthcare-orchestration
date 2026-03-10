<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\DeleteAppointmentNoteCommand;
use App\Modules\Scheduling\Application\Data\AppointmentNoteData;
use App\Modules\Scheduling\Application\Services\AppointmentNoteService;

final class DeleteAppointmentNoteCommandHandler
{
    public function __construct(
        private readonly AppointmentNoteService $appointmentNoteService,
    ) {}

    public function handle(DeleteAppointmentNoteCommand $command): AppointmentNoteData
    {
        return $this->appointmentNoteService->delete($command->appointmentId, $command->noteId);
    }
}
