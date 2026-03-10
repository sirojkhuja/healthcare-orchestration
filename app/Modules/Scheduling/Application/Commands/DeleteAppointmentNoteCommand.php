<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class DeleteAppointmentNoteCommand
{
    public function __construct(
        public string $appointmentId,
        public string $noteId,
    ) {}
}
