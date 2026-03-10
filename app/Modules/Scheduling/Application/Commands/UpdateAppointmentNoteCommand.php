<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class UpdateAppointmentNoteCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $appointmentId,
        public string $noteId,
        public array $attributes,
    ) {}
}
