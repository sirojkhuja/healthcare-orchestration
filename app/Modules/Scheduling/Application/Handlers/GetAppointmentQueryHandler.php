<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Queries\GetAppointmentQuery;
use App\Modules\Scheduling\Application\Services\AppointmentAdministrationService;

final class GetAppointmentQueryHandler
{
    public function __construct(
        private readonly AppointmentAdministrationService $appointmentAdministrationService,
    ) {}

    public function handle(GetAppointmentQuery $query): AppointmentData
    {
        return $this->appointmentAdministrationService->get($query->appointmentId);
    }
}
