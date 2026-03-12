<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\DeleteIntegrationCredentialsCommand;
use App\Modules\Integrations\Application\Commands\UpsertIntegrationCredentialsCommand;
use App\Modules\Integrations\Application\Handlers\DeleteIntegrationCredentialsCommandHandler;
use App\Modules\Integrations\Application\Handlers\GetIntegrationCredentialsQueryHandler;
use App\Modules\Integrations\Application\Handlers\UpsertIntegrationCredentialsCommandHandler;
use App\Modules\Integrations\Application\Queries\GetIntegrationCredentialsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegrationCredentialController
{
    public function delete(string $integrationKey, DeleteIntegrationCredentialsCommandHandler $handler): JsonResponse
    {
        return response()->json([
            'status' => 'integration_credentials_deleted',
            'data' => $handler->handle(new DeleteIntegrationCredentialsCommand($integrationKey))->toArray(),
        ]);
    }

    public function show(string $integrationKey, GetIntegrationCredentialsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetIntegrationCredentialsQuery($integrationKey))->toArray(),
        ]);
    }

    public function update(
        string $integrationKey,
        Request $request,
        UpsertIntegrationCredentialsCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'values' => ['required', 'array'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'integration_credentials_updated',
            'data' => $handler->handle(new UpsertIntegrationCredentialsCommand($integrationKey, $validated))->toArray(),
        ]);
    }
}
