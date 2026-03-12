<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\CreateIntegrationWebhookCommand;
use App\Modules\Integrations\Application\Commands\DeleteIntegrationWebhookCommand;
use App\Modules\Integrations\Application\Commands\RotateWebhookSecretCommand;
use App\Modules\Integrations\Application\Handlers\CreateIntegrationWebhookCommandHandler;
use App\Modules\Integrations\Application\Handlers\DeleteIntegrationWebhookCommandHandler;
use App\Modules\Integrations\Application\Handlers\ListIntegrationWebhooksQueryHandler;
use App\Modules\Integrations\Application\Handlers\RotateWebhookSecretCommandHandler;
use App\Modules\Integrations\Application\Queries\ListIntegrationWebhooksQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegrationWebhookController
{
    public function create(
        string $integrationKey,
        Request $request,
        CreateIntegrationWebhookCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'status' => ['sometimes', 'nullable', 'in:active,paused'],
            'secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'integration_webhook_created',
            'data' => $handler->handle(new CreateIntegrationWebhookCommand($integrationKey, $validated))->toArray(),
        ], 201);
    }

    public function delete(
        string $integrationKey,
        string $webhookId,
        DeleteIntegrationWebhookCommandHandler $handler,
    ): JsonResponse {
        return response()->json([
            'status' => 'integration_webhook_deleted',
            'data' => $handler->handle(new DeleteIntegrationWebhookCommand($integrationKey, $webhookId))->toArray(),
        ]);
    }

    public function list(string $integrationKey, ListIntegrationWebhooksQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($webhook): array => $webhook->toArray(),
                $handler->handle(new ListIntegrationWebhooksQuery($integrationKey)),
            ),
        ]);
    }

    public function rotateSecret(
        string $integrationKey,
        string $webhookId,
        Request $request,
        RotateWebhookSecretCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'secret' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'integration_webhook_secret_rotated',
            'data' => $handler->handle(new RotateWebhookSecretCommand($integrationKey, $webhookId, $validated))->toArray(),
        ]);
    }
}
