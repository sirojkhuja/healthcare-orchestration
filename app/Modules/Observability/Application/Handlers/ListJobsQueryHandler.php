<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\JobOverviewData;
use App\Modules\Observability\Application\Queries\ListJobsQuery;
use App\Modules\Observability\Application\Services\JobAdministrationService;

final class ListJobsQueryHandler
{
    public function __construct(private readonly JobAdministrationService $jobAdministrationService) {}

    public function handle(ListJobsQuery $query): JobOverviewData
    {
        return $this->jobAdministrationService->list($query->criteria);
    }
}
