<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\HandleUzumWebhookCommand;
use App\Modules\Integrations\Application\Commands\VerifyUzumWebhookCommand;
use App\Modules\Integrations\Application\Handlers\HandleUzumWebhookCommandHandler;
use App\Modules\Integrations\Application\Handlers\VerifyUzumWebhookCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UzumWebhookController
{
    public function process(Request $request, HandleUzumWebhookCommandHandler $handler): JsonResponse
    {
        $payload = $this->normalizePayload($request->all());

        return response()->json($handler->handle(new HandleUzumWebhookCommand(
            operation: $this->operation($request),
            authorization: $this->authorizationHeader($request),
            rawPayload: $request->getContent() !== '' ? $request->getContent() : json_encode($payload, JSON_THROW_ON_ERROR),
            payload: $payload,
        ))->toArray());
    }

    public function verify(Request $request, VerifyUzumWebhookCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'operation' => ['required', 'string', 'in:check,create,confirm,reverse,status'],
            'authorization' => ['required', 'string', 'max:2048'],
            'payload' => ['required', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        /** @var mixed $operationValue */
        $operationValue = $validated['operation'] ?? null;
        /** @var mixed $authorizationValue */
        $authorizationValue = $validated['authorization'] ?? null;
        /** @var mixed $payloadValue */
        $payloadValue = $validated['payload'] ?? null;
        $payload = $this->normalizePayload(is_array($payloadValue) ? $payloadValue : []);

        return response()->json([
            'status' => 'uzum_webhook_verified',
            'data' => $handler->handle(new VerifyUzumWebhookCommand(
                operation: is_string($operationValue) ? trim($operationValue) : '',
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

    private function operation(Request $request): string
    {
        $operation = $request->query('operation');

        return is_string($operation) ? trim($operation) : '';
    }
}
