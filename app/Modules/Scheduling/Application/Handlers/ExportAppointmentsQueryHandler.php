<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\AppointmentExportData;
use App\Modules\Scheduling\Application\Queries\ExportAppointmentsQuery;
use App\Modules\Scheduling\Application\Services\AppointmentReadService;

final class ExportAppointmentsQueryHandler
{
    public function __construct(
        private readonly AppointmentReadService $appointmentReadService,
    ) {}

    public function handle(ExportAppointmentsQuery $query): AppointmentExportData
    {
        return $this->appointmentReadService->export($query);
    }
}
