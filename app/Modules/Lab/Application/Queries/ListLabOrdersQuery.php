<?php

namespace App\Modules\Lab\Application\Queries;

use App\Modules\Lab\Application\Data\LabOrderSearchCriteria;

final readonly class ListLabOrdersQuery
{
    public function __construct(
        public LabOrderSearchCriteria $criteria,
    ) {}
}
