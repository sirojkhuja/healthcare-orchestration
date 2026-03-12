<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Commands\ReloadRuntimeConfigCommand;
use App\Modules\Observability\Application\Handlers\GetRuntimeConfigQueryHandler;
use App\Modules\Observability\Application\Handlers\ReloadRuntimeConfigCommandHandler;
use App\Modules\Observability\Application\Queries\GetRuntimeConfigQuery;
use Illuminate\Http\JsonResponse;

final class RuntimeConfigController
{
    public function reload(ReloadRuntimeConfigCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'runtime_config_reloaded',
            'data' => $handler->handle(new ReloadRuntimeConfigCommand)->toArray(),
        ]);
    }

    public function show(GetRuntimeConfigQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetRuntimeConfigQuery)->toArray(),
        ]);
    }
}
