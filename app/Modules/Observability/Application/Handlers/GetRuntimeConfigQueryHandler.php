<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\RuntimeConfigData;
use App\Modules\Observability\Application\Queries\GetRuntimeConfigQuery;
use App\Modules\Observability\Application\Services\RuntimeConfigService;

final class GetRuntimeConfigQueryHandler
{
    public function __construct(private readonly RuntimeConfigService $runtimeConfigService) {}

    public function handle(GetRuntimeConfigQuery $query): RuntimeConfigData
    {
        return $this->runtimeConfigService->get();
    }
}
