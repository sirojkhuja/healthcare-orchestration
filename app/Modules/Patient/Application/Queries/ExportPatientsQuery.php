<?php

namespace App\Modules\Patient\Application\Queries;

use App\Modules\Patient\Application\Data\PatientSearchCriteria;

final readonly class ExportPatientsQuery
{
    public function __construct(
        public PatientSearchCriteria $criteria,
        public string $format = 'csv',
    ) {}
}
