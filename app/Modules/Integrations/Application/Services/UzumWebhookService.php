<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentGatewayRegistry;
use App\Modules\Billing\Application\Contracts\ServiceIdAwareWebhookPaymentGateway;
use App\Modules\Integrations\Application\Data\UzumWebhookResponseData;
use App\Modules\Integrations\Application\Data\UzumWebhookVerificationData;
use App\Modules\Integrations\Application\Exceptions\UzumWebhookException;
use App\Shared\Application\Contracts\ObservabilityMetricRecorder;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UzumWebhookService
{
    public function __construct(
        private readonly PaymentGatewayRegistry $paymentGatewayRegistry,
        private readonly UzumPaymentResolver $uzumPaymentResolver,
        private readonly UzumWebhookMutationService $uzumWebhookMutationService,
        private readonly ObservabilityMetricRecorder $metricRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(string $operation, string $authorization, string $rawPayload, array $payload): UzumWebhookResponseData
    {
        try {
            $resolvedOperation = $this->uzumPaymentResolver->requestedOperation($operation);
            $this->assertVerified($authorization, $rawPayload, $payload);
            $this->assertServiceId($payload);

            $response = match ($resolvedOperation) {
                'check' => $this->uzumWebhookMutationService->check($payload, $authorization),
                'confirm' => $this->uzumWebhookMutationService->confirm($payload, $authorization),
                'create' => $this->uzumWebhookMutationService->create($payload, $authorization),
                'reverse' => $this->uzumWebhookMutationService->reverse($payload, $authorization),
                'status' => $this->uzumWebhookMutationService->status($payload, $authorization),
            };

            return new UzumWebhookResponseData($response);
        } catch (UzumWebhookException $exception) {
            $this->metricRecorder->recordIntegrationError('uzum', 'webhook.process', 'uzum_webhook_exception');
            Log::warning('integration.webhook.failed', [
                'provider_key' => 'uzum',
                'operation' => 'webhook.process',
                'error_message' => $exception->getMessage(),
            ]);
            $response = $this->errorResponse(
                code: $exception->uzumCode,
                message: $exception->getMessage(),
                payload: $payload,
            );
            $this->uzumWebhookMutationService->storeFailure(
                method: $this->safeOperation($operation),
                payload: $payload,
                authorization: $authorization,
                response: $response,
            );

            return new UzumWebhookResponseData($response);
        } catch (Throwable) {
            $this->metricRecorder->recordIntegrationError('uzum', 'webhook.process', 'system_error');
            Log::warning('integration.webhook.failed', [
                'provider_key' => 'uzum',
                'operation' => 'webhook.process',
            ]);
            $response = $this->errorResponse(
                code: 'PROCESSING_FAILED',
                message: 'The Uzum webhook could not be processed.',
                payload: $payload,
            );
            $this->uzumWebhookMutationService->storeFailure(
                method: $this->safeOperation($operation),
                payload: $payload,
                authorization: $authorization,
                response: $response,
            );

            return new UzumWebhookResponseData($response);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(string $operation, string $authorization, string $rawPayload, array $payload): UzumWebhookVerificationData
    {
        try {
            $resolvedOperation = $this->uzumPaymentResolver->requestedOperation($operation);
            $valid = $this->gateway()->verifyWebhookSignature(
                $authorization,
                $rawPayload !== '' ? $rawPayload : json_encode($payload, JSON_THROW_ON_ERROR),
            ) && $this->gateway()->configuredServiceId() === $this->uzumPaymentResolver->serviceId($payload);

            return new UzumWebhookVerificationData(
                providerKey: 'uzum',
                valid: $valid,
                operation: $resolvedOperation,
                transactionId: $this->safeTransactionId($payload),
                paymentId: $this->safePaymentId($payload),
            );
        } catch (Throwable) {
            return new UzumWebhookVerificationData(
                providerKey: 'uzum',
                valid: false,
                operation: $this->safeOperation($operation),
                transactionId: $this->safeTransactionId($payload),
                paymentId: $this->safePaymentId($payload),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertServiceId(array $payload): void
    {
        if ($this->gateway()->configuredServiceId() !== $this->uzumPaymentResolver->serviceId($payload)) {
            throw new UzumWebhookException('SERVICE_MISMATCH', 'The Uzum serviceId does not match the configured merchant service.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertVerified(string $authorization, string $rawPayload, array $payload): void
    {
        $body = $rawPayload !== '' ? $rawPayload : json_encode($payload, JSON_THROW_ON_ERROR);

        if (! $this->gateway()->verifyWebhookSignature($authorization, $body)) {
            $this->metricRecorder->recordWebhookVerificationFailure('uzum');
            $this->metricRecorder->recordIntegrationError('uzum', 'webhook.process', 'invalid_signature');
            Log::warning('integration.webhook.verification_failed', [
                'provider_key' => 'uzum',
                'operation' => 'webhook.process',
            ]);
            throw new UzumWebhookException('AUTHENTICATION_FAILED', 'The Uzum webhook authorization header is invalid.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function errorResponse(string $code, string $message, array $payload): array
    {
        return [
            'status' => 'ERROR',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'data' => [
                'transaction_id' => $this->safeTransactionId($payload),
                'payment_id' => $this->safePaymentId($payload),
            ],
        ];
    }

    private function gateway(): ServiceIdAwareWebhookPaymentGateway
    {
        $gateway = $this->paymentGatewayRegistry->resolve('uzum');

        if (! $gateway instanceof ServiceIdAwareWebhookPaymentGateway) {
            throw new \LogicException('The configured Uzum payment gateway is invalid.');
        }

        return $gateway;
    }

    private function safeOperation(string $operation): string
    {
        $normalized = strtolower(trim($operation));

        return in_array($normalized, ['check', 'create', 'confirm', 'reverse', 'status'], true)
            ? $normalized
            : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function safePaymentId(array $payload): ?string
    {
        try {
            return $this->uzumPaymentResolver->paymentId($payload);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function safeTransactionId(array $payload): ?string
    {
        try {
            return $this->uzumPaymentResolver->transactionId($payload);
        } catch (Throwable) {
            return null;
        }
    }
}
