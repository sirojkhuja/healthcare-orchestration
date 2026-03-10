<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\AppointmentParticipantData;
use App\Modules\Scheduling\Application\Queries\ListAppointmentParticipantsQuery;
use App\Modules\Scheduling\Application\Services\AppointmentParticipantService;

final class ListAppointmentParticipantsQueryHandler
{
    public function __construct(
        private readonly AppointmentParticipantService $appointmentParticipantService,
    ) {}

    /**
     * @return list<AppointmentParticipantData>
     */
    public function handle(ListAppointmentParticipantsQuery $query): array
    {
        return $this->appointmentParticipantService->list($query->appointmentId);
    }
}
