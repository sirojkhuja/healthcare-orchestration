<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\ReloadLoggingPipelinesCommand;
use App\Modules\Observability\Application\Data\LoggingPipelineData;
use App\Modules\Observability\Application\Services\LoggingPipelineService;

final class ReloadLoggingPipelinesCommandHandler
{
    public function __construct(private readonly LoggingPipelineService $loggingPipelineService) {}

    /**
     * @return list<LoggingPipelineData>
     */
    public function handle(ReloadLoggingPipelinesCommand $command): array
    {
        return $this->loggingPipelineService->reload($command->pipelines);
    }
}
