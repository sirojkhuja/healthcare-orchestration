<?php

namespace App\Modules\Scheduling\Application\Queries;

use App\Modules\Scheduling\Application\Data\AppointmentSearchCriteria;

final readonly class ExportAppointmentsQuery
{
    public function __construct(
        public AppointmentSearchCriteria $criteria,
        public string $format = 'csv',
    ) {}
}
