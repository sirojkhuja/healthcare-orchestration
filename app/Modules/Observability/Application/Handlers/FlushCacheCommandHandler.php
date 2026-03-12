<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\FlushCacheCommand;
use App\Modules\Observability\Application\Data\CacheOperationData;
use App\Modules\Observability\Application\Services\CacheAdministrationService;

final class FlushCacheCommandHandler
{
    public function __construct(private readonly CacheAdministrationService $cacheAdministrationService) {}

    public function handle(FlushCacheCommand $command): CacheOperationData
    {
        return $this->cacheAdministrationService->flush(
            $command->domains,
            $command->includeGlobalReferenceData,
        );
    }
}
