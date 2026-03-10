<?php

namespace App\Modules\Lab\Presentation\Http\Controllers;

use App\Modules\Lab\Application\Handlers\GetLabResultQueryHandler;
use App\Modules\Lab\Application\Handlers\ListLabResultsQueryHandler;
use App\Modules\Lab\Application\Queries\GetLabResultQuery;
use App\Modules\Lab\Application\Queries\ListLabResultsQuery;
use Illuminate\Http\JsonResponse;

final class LabResultController
{
    public function list(string $orderId, ListLabResultsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($result): array => $result->toArray(),
                $handler->handle(new ListLabResultsQuery($orderId)),
            ),
        ]);
    }

    public function show(string $orderId, string $resultId, GetLabResultQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetLabResultQuery($orderId, $resultId))->toArray(),
        ]);
    }
}
