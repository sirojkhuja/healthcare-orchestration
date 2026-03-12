<?php

namespace App\Modules\Observability\Application\Queries;

use App\Modules\Observability\Application\Data\JobSearchCriteria;

final readonly class ListJobsQuery
{
    public function __construct(public JobSearchCriteria $criteria) {}
}
