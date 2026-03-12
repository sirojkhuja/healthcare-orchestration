<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\RefreshIntegrationTokensCommand;
use App\Modules\Integrations\Application\Commands\RevokeIntegrationTokenCommand;
use App\Modules\Integrations\Application\Handlers\ListIntegrationTokensQueryHandler;
use App\Modules\Integrations\Application\Handlers\RefreshIntegrationTokensCommandHandler;
use App\Modules\Integrations\Application\Handlers\RevokeIntegrationTokenCommandHandler;
use App\Modules\Integrations\Application\Queries\ListIntegrationTokensQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegrationTokenController
{
    public function list(string $integrationKey, ListIntegrationTokensQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($token): array => $token->toArray(),
                $handler->handle(new ListIntegrationTokensQuery($integrationKey)),
            ),
        ]);
    }

    public function refresh(
        string $integrationKey,
        Request $request,
        RefreshIntegrationTokensCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'token_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        /** @var array<string, mixed> $validated */
        $tokenId = null;

        if (isset($validated['token_id']) && is_string($validated['token_id'])) {
            $tokenId = $validated['token_id'];
        }

        return response()->json([
            'status' => 'integration_token_refreshed',
            'data' => $handler->handle(new RefreshIntegrationTokensCommand(
                integrationKey: $integrationKey,
                tokenId: $tokenId,
            ))->toArray(),
        ]);
    }

    public function revoke(
        string $integrationKey,
        string $tokenId,
        RevokeIntegrationTokenCommandHandler $handler,
    ): JsonResponse {
        return response()->json([
            'status' => 'integration_token_revoked',
            'data' => $handler->handle(new RevokeIntegrationTokenCommand($integrationKey, $tokenId))->toArray(),
        ]);
    }
}
