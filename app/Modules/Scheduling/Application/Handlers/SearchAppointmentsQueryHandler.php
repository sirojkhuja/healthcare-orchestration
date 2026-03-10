<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Queries\SearchAppointmentsQuery;
use App\Modules\Scheduling\Application\Services\AppointmentReadService;

final class SearchAppointmentsQueryHandler
{
    public function __construct(
        private readonly AppointmentReadService $appointmentReadService,
    ) {}

    /**
     * @return list<\App\Modules\Scheduling\Application\Data\AppointmentData>
     */
    public function handle(SearchAppointmentsQuery $query): array
    {
        return $this->appointmentReadService->search($query->criteria);
    }
}
