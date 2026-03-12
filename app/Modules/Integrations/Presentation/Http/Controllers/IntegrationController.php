<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\DisableIntegrationCommand;
use App\Modules\Integrations\Application\Commands\EnableIntegrationCommand;
use App\Modules\Integrations\Application\Handlers\DisableIntegrationCommandHandler;
use App\Modules\Integrations\Application\Handlers\EnableIntegrationCommandHandler;
use App\Modules\Integrations\Application\Handlers\GetIntegrationQueryHandler;
use App\Modules\Integrations\Application\Handlers\ListIntegrationsQueryHandler;
use App\Modules\Integrations\Application\Queries\GetIntegrationQuery;
use App\Modules\Integrations\Application\Queries\ListIntegrationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegrationController
{
    public function disable(string $integrationKey, DisableIntegrationCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'integration_disabled',
            'data' => $handler->handle(new DisableIntegrationCommand($integrationKey))->toArray(),
        ]);
    }

    public function enable(string $integrationKey, EnableIntegrationCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'integration_enabled',
            'data' => $handler->handle(new EnableIntegrationCommand($integrationKey))->toArray(),
        ]);
    }

    public function list(Request $request, ListIntegrationsQueryHandler $handler): JsonResponse
    {
        $enabled = $request->query('enabled');
        $resolvedEnabled = is_string($enabled) && $enabled !== ''
            ? filter_var($enabled, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            : null;

        return response()->json([
            'data' => array_map(
                static fn ($integration): array => $integration->toArray(),
                $handler->handle(new ListIntegrationsQuery(
                    category: $request->string('category')->trim()->value() ?: null,
                    enabled: $resolvedEnabled,
                )),
            ),
        ]);
    }

    public function show(string $integrationKey, GetIntegrationQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetIntegrationQuery($integrationKey))->toArray(),
        ]);
    }
}
