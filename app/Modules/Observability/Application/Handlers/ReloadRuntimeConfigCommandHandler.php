<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\ReloadRuntimeConfigCommand;
use App\Modules\Observability\Application\Data\RuntimeConfigData;
use App\Modules\Observability\Application\Services\RuntimeConfigService;

final class ReloadRuntimeConfigCommandHandler
{
    public function __construct(private readonly RuntimeConfigService $runtimeConfigService) {}

    public function handle(ReloadRuntimeConfigCommand $command): RuntimeConfigData
    {
        return $this->runtimeConfigService->reload();
    }
}
