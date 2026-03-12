<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\RebuildCachesCommand;
use App\Modules\Observability\Application\Data\CacheOperationData;
use App\Modules\Observability\Application\Services\CacheAdministrationService;

final class RebuildCachesCommandHandler
{
    public function __construct(private readonly CacheAdministrationService $cacheAdministrationService) {}

    public function handle(RebuildCachesCommand $command): CacheOperationData
    {
        return $this->cacheAdministrationService->rebuild(
            $command->domains,
            $command->includeGlobalReferenceData,
        );
    }
}
