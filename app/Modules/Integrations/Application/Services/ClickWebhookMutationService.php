<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;
use App\Modules\Billing\Domain\Payments\PaymentActor;
use App\Modules\Integrations\Application\Contracts\PaymentWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\PaymentWebhookDeliveryData;
use Carbon\CarbonImmutable;

final class ClickWebhookMutationService
{
    public function __construct(
        private readonly PaymentWorkflowService $paymentWorkflowService,
        private readonly PaymentWebhookDeliveryRepository $paymentWebhookDeliveryRepository,
        private readonly ClickPaymentResolver $clickPaymentResolver,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function complete(array $payload): array
    {
        $providerTransactionId = $this->clickPaymentResolver->providerTransactionId($payload);
        $existing = $this->existingResponse('complete', $providerTransactionId);

        if ($existing !== null) {
            return $existing;
        }

        $payment = $this->clickPaymentResolver->paymentByPrepareReference($payload);
        $merchantTransactionPayment = $this->clickPaymentResolver->paymentByMerchantTransactionId($payload);
        $providerPaymentId = $this->clickPaymentResolver->providerPaymentId($payload);

        if ($merchantTransactionPayment->paymentId !== $payment->paymentId) {
            $response = $this->errorResponse($payload, -6, 'Transaction does not exist');
            $this->storeDelivery('complete', $payload, $payment, $response, 'rejected');

            return $response;
        }

        $this->clickPaymentResolver->assertAmountMatches($payment, $payload);
        $response = $this->isCanceledByClick($payload)
            ? $this->cancelFlow($payload, $payment, $providerPaymentId)
            : $this->captureFlow($payload, $payment, $providerPaymentId);

        return $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function existingResponse(string $method, string $providerTransactionId): ?array
    {
        $existing = $this->paymentWebhookDeliveryRepository->findByReplayKey('click', $method, $providerTransactionId);

        return $existing instanceof PaymentWebhookDeliveryData && is_array($existing->response)
            ? $existing->response
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function captureFlow(array $payload, PaymentData $payment, string $providerPaymentId): array
    {
        $response = match ($payment->status) {
            'captured', 'refunded' => $this->errorResponse($payload, -4, 'Already paid'),
            'canceled' => $this->errorResponse($payload, -9, 'Transaction cancelled'),
            'initiated', 'pending' => $this->successCompleteResponse(
                $payload,
                $this->paymentWorkflowService->capture(
                    paymentId: $payment->paymentId,
                    providerPaymentId: $providerPaymentId,
                    providerStatus: 'completed',
                    actor: $this->systemActor(),
                    metadata: $this->metadata('complete', $payload),
                    tenantId: $payment->tenantId,
                ),
            ),
            default => $this->errorResponse($payload, -7, 'Failed to update user'),
        };
        /** @var array<string, mixed> $response */
        $this->storeDelivery('complete', $payload, $payment, $response, $response['error'] === 0 ? 'processed' : 'rejected');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cancelFlow(array $payload, PaymentData $payment, string $providerPaymentId): array
    {
        $response = match ($payment->status) {
            'captured', 'refunded' => $this->errorResponse($payload, -4, 'Already paid'),
            'canceled' => $this->errorResponse($payload, -9, 'Transaction cancelled'),
            'initiated', 'pending' => $this->errorResponse($payload, -9, 'Transaction cancelled'),
            default => $this->errorResponse($payload, -7, 'Failed to update user'),
        };

        if (in_array($payment->status, ['initiated', 'pending'], true)) {
            $this->paymentWorkflowService->cancel(
                paymentId: $payment->paymentId,
                reason: 'Click canceled the payment during Complete.',
                providerPaymentId: $providerPaymentId,
                providerStatus: 'cancelled',
                actor: $this->systemActor(),
                metadata: $this->metadata('complete', $payload),
                tenantId: $payment->tenantId,
            );
        }

        $this->storeDelivery('complete', $payload, $payment, $response, 'rejected');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isCanceledByClick(array $payload): bool
    {
        /** @psalm-suppress MixedAssignment */
        $error = $payload['error'] ?? null;

        return is_numeric($error) && (int) $error < 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function metadata(string $method, array $payload): array
    {
        return [
            'method' => $method,
            'provider_key' => 'click',
            'provider_transaction_id' => $this->clickPaymentResolver->providerTransactionId($payload),
            'source' => 'webhook',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function prepare(array $payload): array
    {
        $providerTransactionId = $this->clickPaymentResolver->providerTransactionId($payload);
        $existing = $this->existingResponse('prepare', $providerTransactionId);

        if ($existing !== null) {
            return $existing;
        }

        $payment = $this->clickPaymentResolver->paymentByMerchantTransactionId($payload);
        $this->clickPaymentResolver->assertAmountMatches($payment, $payload);
        $providerPaymentId = $this->clickPaymentResolver->providerPaymentId($payload);

        $response = match ($payment->status) {
            'captured', 'refunded' => $this->errorResponse($payload, -4, 'Already paid'),
            'canceled' => $this->errorResponse($payload, -9, 'Transaction cancelled'),
            'initiated' => $this->successPrepareResponse(
                $payload,
                $this->paymentWorkflowService->markPending(
                    paymentId: $payment->paymentId,
                    providerPaymentId: $providerPaymentId,
                    providerStatus: 'prepared',
                    actor: $this->systemActor(),
                    metadata: $this->metadata('prepare', $payload),
                    tenantId: $payment->tenantId,
                ),
            ),
            'pending' => $this->successPrepareResponse($payload, $payment),
            default => $this->errorResponse($payload, -7, 'Failed to update user'),
        };
        /** @var array<string, mixed> $response */
        $this->storeDelivery('prepare', $payload, $payment, $response, $response['error'] === 0 ? 'processed' : 'rejected');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    public function storeFailure(string $method, array $payload, array $response): void
    {
        /** @psalm-suppress MixedAssignment */
        $providerTransactionId = $payload['click_trans_id'] ?? null;
        $replayKey = is_scalar($providerTransactionId) ? (string) $providerTransactionId : null;

        if ($replayKey !== null && $replayKey !== '' && $this->paymentWebhookDeliveryRepository->findByReplayKey('click', $method, $replayKey) instanceof PaymentWebhookDeliveryData) {
            return;
        }

        $delivery = $this->paymentWebhookDeliveryRepository->create([
            'provider_key' => 'click',
            'method' => $method,
            'replay_key' => $replayKey,
            'provider_transaction_id' => $replayKey,
            'request_id' => $this->scalarString($payload['click_paydoc_id'] ?? null),
            'payment_id' => null,
            'resolved_tenant_id' => null,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'auth_hash' => hash('sha256', $this->scalarString($payload['sign_string'] ?? null) ?? ''),
            'provider_time_millis' => $this->providerTimeMillis($payload),
            'outcome' => 'rejected',
            'provider_error_code' => $this->responseErrorCode($response),
            'provider_error_message' => $this->scalarString($response['error_note'] ?? null),
            'processed_at' => CarbonImmutable::now(),
            'payload' => $payload,
            'response' => $response,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payment_webhooks.processed',
            objectType: 'payment_webhook_delivery',
            objectId: $delivery->deliveryRecordId,
            after: $delivery->toArray(),
            metadata: [
                'method' => $method,
                'provider_key' => 'click',
                'provider_transaction_id' => $replayKey,
                'source' => 'webhook',
            ],
            tenantId: null,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function errorResponse(array $payload, int $error, string $errorNote): array
    {
        $response = [
            'error' => $error,
            'error_note' => $errorNote,
        ];

        if (isset($payload['click_trans_id'])) {
            $response['click_trans_id'] = $this->scalarString($payload['click_trans_id']);
        }

        if (isset($payload['merchant_trans_id'])) {
            $response['merchant_trans_id'] = $this->scalarString($payload['merchant_trans_id']);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    private function storeDelivery(
        string $method,
        array $payload,
        PaymentData $payment,
        array $response,
        string $outcome,
    ): void {
        $providerTransactionId = $this->clickPaymentResolver->providerTransactionId($payload);

        if ($this->paymentWebhookDeliveryRepository->findByReplayKey('click', $method, $providerTransactionId) instanceof PaymentWebhookDeliveryData) {
            return;
        }

        $delivery = $this->paymentWebhookDeliveryRepository->create([
            'provider_key' => 'click',
            'method' => $method,
            'replay_key' => $providerTransactionId,
            'provider_transaction_id' => $providerTransactionId,
            'request_id' => $this->scalarString($payload['click_paydoc_id'] ?? null),
            'payment_id' => $payment->paymentId,
            'resolved_tenant_id' => $payment->tenantId,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'auth_hash' => hash('sha256', $this->scalarString($payload['sign_string'] ?? null) ?? ''),
            'provider_time_millis' => $this->providerTimeMillis($payload),
            'outcome' => $outcome,
            'provider_error_code' => $this->responseErrorCode($response),
            'provider_error_message' => $this->scalarString($response['error_note'] ?? null),
            'processed_at' => CarbonImmutable::now(),
            'payload' => $payload,
            'response' => $response,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payment_webhooks.processed',
            objectType: 'payment_webhook_delivery',
            objectId: $delivery->deliveryRecordId,
            after: $delivery->toArray(),
            metadata: $this->metadata($method, $payload),
            tenantId: $payment->tenantId,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerTimeMillis(array $payload): ?int
    {
        try {
            return $this->clickPaymentResolver->providerTimeMillis($payload);
        } catch (\Throwable) {
            return null;
        }
    }

    private function scalarString(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function responseErrorCode(array $response): string
    {
        /** @psalm-suppress MixedAssignment */
        $error = $response['error'] ?? null;

        if (is_int($error) || is_float($error) || is_string($error)) {
            return (string) $error;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function successCompleteResponse(array $payload, PaymentData $payment): array
    {
        return [
            'error' => 0,
            'error_note' => 'Success',
            'click_trans_id' => $this->clickPaymentResolver->providerTransactionId($payload),
            'merchant_trans_id' => $payment->paymentId,
            'merchant_confirm_id' => $this->clickPaymentResolver->providerTransactionId($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function successPrepareResponse(array $payload, PaymentData $payment): array
    {
        return [
            'error' => 0,
            'error_note' => 'Success',
            'click_trans_id' => $this->clickPaymentResolver->providerTransactionId($payload),
            'merchant_trans_id' => $payment->paymentId,
            'merchant_prepare_id' => $this->clickPaymentResolver->providerTransactionId($payload),
        ];
    }

    private function systemActor(): PaymentActor
    {
        return new PaymentActor(type: 'system', name: 'click-webhook');
    }
}
