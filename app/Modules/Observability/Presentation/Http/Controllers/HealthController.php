<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Handlers\HealthQueryHandler;
use App\Modules\Observability\Application\Handlers\LivenessQueryHandler;
use App\Modules\Observability\Application\Handlers\MetricsQueryHandler;
use App\Modules\Observability\Application\Handlers\ReadinessQueryHandler;
use App\Modules\Observability\Application\Handlers\VersionQueryHandler;
use App\Modules\Observability\Application\Queries\HealthQuery;
use App\Modules\Observability\Application\Queries\LivenessQuery;
use App\Modules\Observability\Application\Queries\MetricsQuery;
use App\Modules\Observability\Application\Queries\ReadinessQuery;
use App\Modules\Observability\Application\Queries\VersionQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class HealthController
{
    public function health(HealthQueryHandler $handler): JsonResponse
    {
        $report = $handler->handle(new HealthQuery);
        $statusCode = $report->status === 'failing' ? 503 : 200;

        return response()->json($report->toArray(), $statusCode);
    }

    public function live(LivenessQueryHandler $handler): JsonResponse
    {
        return response()->json($handler->handle(new LivenessQuery)->toArray());
    }

    public function metrics(MetricsQueryHandler $handler): Response
    {
        return response($handler->handle(new MetricsQuery), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
        ]);
    }

    public function ready(ReadinessQueryHandler $handler): JsonResponse
    {
        $report = $handler->handle(new ReadinessQuery);
        $statusCode = $report->status === 'ready' ? 200 : 503;

        return response()->json($report->toArray(), $statusCode);
    }

    public function version(VersionQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new VersionQuery)->toArray(),
        ]);
    }
}
