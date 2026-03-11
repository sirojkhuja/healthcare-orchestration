<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\HandlePaymeWebhookCommand;
use App\Modules\Integrations\Application\Commands\VerifyPaymeWebhookCommand;
use App\Modules\Integrations\Application\Handlers\HandlePaymeWebhookCommandHandler;
use App\Modules\Integrations\Application\Handlers\VerifyPaymeWebhookCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymeWebhookController
{
    public function process(Request $request, HandlePaymeWebhookCommandHandler $handler): JsonResponse
    {
        $rawPayload = $request->getContent();
        /** @var mixed $decoded */
        $decoded = json_decode($rawPayload, true);

        return response()->json($handler->handle(new HandlePaymeWebhookCommand(
            authorization: $this->authorizationHeader($request),
            rawPayload: $rawPayload,
            payload: $decoded,
        ))->toArray());
    }

    public function verify(Request $request, VerifyPaymeWebhookCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'authorization' => ['required', 'string', 'max:2048'],
            'payload' => ['required', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $payload = $this->normalizePayload($validated['payload'] ?? null);
        /** @var mixed $authorizationValue */
        $authorizationValue = $validated['authorization'] ?? null;

        return response()->json([
            'status' => 'payme_webhook_verified',
            'data' => $handler->handle(new VerifyPaymeWebhookCommand(
                authorization: is_string($authorizationValue) ? trim($authorizationValue) : '',
                rawPayload: json_encode($payload, JSON_THROW_ON_ERROR),
                payload: $payload,
            ))->toArray(),
        ]);
    }

    private function authorizationHeader(Request $request): string
    {
        $authorization = $request->header('Authorization');

        return is_string($authorization) ? trim($authorization) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
