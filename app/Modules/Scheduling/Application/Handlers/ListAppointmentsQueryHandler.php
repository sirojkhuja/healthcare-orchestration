<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Queries\ListAppointmentsQuery;
use App\Modules\Scheduling\Application\Services\AppointmentAdministrationService;

final class ListAppointmentsQueryHandler
{
    public function __construct(
        private readonly AppointmentAdministrationService $appointmentAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\Scheduling\Application\Data\AppointmentData>
     */
    public function handle(ListAppointmentsQuery $query): array
    {
        return $this->appointmentAdministrationService->list();
    }
}
