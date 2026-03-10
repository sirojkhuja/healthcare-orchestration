<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\AppointmentNoteData;
use App\Modules\Scheduling\Application\Queries\ListAppointmentNotesQuery;
use App\Modules\Scheduling\Application\Services\AppointmentNoteService;

final class ListAppointmentNotesQueryHandler
{
    public function __construct(
        private readonly AppointmentNoteService $appointmentNoteService,
    ) {}

    /**
     * @return list<AppointmentNoteData>
     */
    public function handle(ListAppointmentNotesQuery $query): array
    {
        return $this->appointmentNoteService->list($query->appointmentId);
    }
}
