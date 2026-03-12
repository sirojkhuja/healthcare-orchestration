<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\LoggingPipelineData;
use App\Modules\Observability\Application\Queries\ListLoggingPipelinesQuery;
use App\Modules\Observability\Application\Services\LoggingPipelineService;

final class ListLoggingPipelinesQueryHandler
{
    public function __construct(private readonly LoggingPipelineService $loggingPipelineService) {}

    /**
     * @return list<LoggingPipelineData>
     */
    public function handle(ListLoggingPipelinesQuery $query): array
    {
        return $this->loggingPipelineService->list();
    }
}
