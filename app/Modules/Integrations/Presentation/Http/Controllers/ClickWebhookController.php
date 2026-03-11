<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\HandleClickWebhookCommand;
use App\Modules\Integrations\Application\Commands\VerifyClickWebhookCommand;
use App\Modules\Integrations\Application\Handlers\HandleClickWebhookCommandHandler;
use App\Modules\Integrations\Application\Handlers\VerifyClickWebhookCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClickWebhookController
{
    public function process(Request $request, HandleClickWebhookCommandHandler $handler): JsonResponse
    {
        $payload = $this->normalizePayload($request->all());

        return response()->json($handler->handle(new HandleClickWebhookCommand(
            rawPayload: json_encode($payload, JSON_THROW_ON_ERROR),
            payload: $payload,
        ))->toArray());
    }

    public function verify(Request $request, VerifyClickWebhookCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'array'],
        ]);
        /** @var mixed $payloadCandidate */
        $payloadCandidate = $validated['payload'] ?? null;
        $payload = $this->normalizePayload(is_array($payloadCandidate) ? $payloadCandidate : []);

        return response()->json([
            'status' => 'click_webhook_verified',
            'data' => $handler->handle(new VerifyClickWebhookCommand(
                rawPayload: json_encode($payload, JSON_THROW_ON_ERROR),
                payload: $payload,
            ))->toArray(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                /** @psalm-suppress MixedAssignment */
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
