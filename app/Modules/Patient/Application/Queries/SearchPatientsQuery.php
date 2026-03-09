<?php

namespace App\Modules\Patient\Application\Queries;

use App\Modules\Patient\Application\Data\PatientSearchCriteria;

final readonly class SearchPatientsQuery
{
    public function __construct(
        public PatientSearchCriteria $criteria,
    ) {}
}
