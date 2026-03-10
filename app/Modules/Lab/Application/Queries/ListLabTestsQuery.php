<?php

namespace App\Modules\Lab\Application\Queries;

use App\Modules\Lab\Application\Data\LabTestListCriteria;

final readonly class ListLabTestsQuery
{
    public function __construct(
        public LabTestListCriteria $criteria,
    ) {}
}
