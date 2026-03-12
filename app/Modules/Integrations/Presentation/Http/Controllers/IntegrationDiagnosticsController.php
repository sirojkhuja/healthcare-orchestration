<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\TestIntegrationConnectionCommand;
use App\Modules\Integrations\Application\Handlers\IntegrationHealthQueryHandler;
use App\Modules\Integrations\Application\Handlers\ListIntegrationLogsQueryHandler;
use App\Modules\Integrations\Application\Handlers\TestIntegrationConnectionCommandHandler;
use App\Modules\Integrations\Application\Queries\IntegrationHealthQuery;
use App\Modules\Integrations\Application\Queries\ListIntegrationLogsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegrationDiagnosticsController
{
    public function health(string $integrationKey, IntegrationHealthQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new IntegrationHealthQuery($integrationKey))->toArray(),
        ]);
    }

    public function logs(string $integrationKey, Request $request, ListIntegrationLogsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($log): array => $log->toArray(),
                $handler->handle(new ListIntegrationLogsQuery(
                    integrationKey: $integrationKey,
                    level: $request->string('level')->trim()->value() ?: null,
                    event: $request->string('event')->trim()->value() ?: null,
                    limit: max(1, min($request->integer('limit', 50), 100)),
                )),
            ),
        ]);
    }

    public function testConnection(string $integrationKey, TestIntegrationConnectionCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'integration_connection_tested',
            'data' => $handler->handle(new TestIntegrationConnectionCommand($integrationKey))->toArray(),
        ]);
    }
}
