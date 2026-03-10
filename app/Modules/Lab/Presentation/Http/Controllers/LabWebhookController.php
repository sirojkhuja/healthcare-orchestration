<?php

namespace App\Modules\Lab\Presentation\Http\Controllers;

use App\Modules\Lab\Application\Commands\ReceiveLabResultWebhookCommand;
use App\Modules\Lab\Application\Commands\VerifyLabWebhookCommand;
use App\Modules\Lab\Application\Handlers\ReceiveLabResultWebhookCommandHandler;
use App\Modules\Lab\Application\Handlers\VerifyLabWebhookCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class LabWebhookController
{
    public function process(string $provider, Request $request, ReceiveLabResultWebhookCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->processRules());
        /** @var array<string, mixed> $validated */
        $signature = $this->signatureHeaderOrFail($request);
        $rawPayload = $request->getContent();

        if ($rawPayload === '') {
            $rawPayload = json_encode($validated, JSON_THROW_ON_ERROR);
        }

        $result = $handler->handle(new ReceiveLabResultWebhookCommand(
            providerKey: $provider,
            signature: $signature,
            rawPayload: $rawPayload,
            payload: $validated,
        ));

        return response()->json([
            'status' => 'lab_webhook_processed',
            'data' => $result->toArray(),
        ]);
    }

    public function verify(string $provider, Request $request, VerifyLabWebhookCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'max:2048'],
            'payload' => ['required', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $payload = $this->normalizePayload($validated['payload'] ?? null);
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        /** @psalm-suppress MixedAssignment */
        $signatureValue = $validated['signature'] ?? null;
        $signature = is_string($signatureValue) ? $signatureValue : '';
        $result = $handler->handle(new VerifyLabWebhookCommand(
            providerKey: $provider,
            signature: $signature,
            rawPayload: $rawPayload,
            payload: $payload,
        ));

        return response()->json([
            'status' => 'lab_webhook_verified',
            'data' => $result->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function processRules(): array
    {
        return [
            'delivery_id' => ['required', 'string', 'max:191'],
            'external_order_id' => ['required', 'string', 'max:191'],
            'status' => ['required', 'string', 'in:sent,specimen_collected,specimen_received,completed,canceled'],
            'occurred_at' => ['required', 'date'],
            'results' => ['sometimes', 'array'],
            'results.*.external_result_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'results.*.status' => ['required', 'string', 'in:preliminary,final,corrected'],
            'results.*.observed_at' => ['required', 'date'],
            'results.*.received_at' => ['required', 'date'],
            'results.*.value_type' => ['required', 'string', 'in:numeric,text,boolean,json'],
            'results.*.value_numeric' => ['sometimes', 'nullable'],
            'results.*.value_text' => ['sometimes', 'nullable', 'string'],
            'results.*.value_boolean' => ['sometimes', 'nullable', 'boolean'],
            'results.*.value_json' => ['sometimes', 'nullable', 'array'],
            'results.*.unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'results.*.reference_range' => ['sometimes', 'nullable', 'string', 'max:255'],
            'results.*.abnormal_flag' => ['sometimes', 'nullable', 'string', 'in:normal,low,high,critical,abnormal'],
            'results.*.notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'results.*.raw_payload' => ['sometimes', 'nullable', 'array'],
        ];
    }

    private function signatureHeaderOrFail(Request $request): string
    {
        /** @var mixed $configuredHeaderName */
        $configuredHeaderName = config('medflow.lab.webhook_signature_header', 'X-Lab-Signature');
        $headerName = is_string($configuredHeaderName) && trim($configuredHeaderName) !== ''
            ? $configuredHeaderName
            : 'X-Lab-Signature';
        $signature = $request->header($headerName);

        if (! is_string($signature) || trim($signature) === '') {
            throw ValidationException::withMessages([
                $headerName => ['The webhook signature header is required for this operation.'],
            ]);
        }

        return trim($signature);
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
