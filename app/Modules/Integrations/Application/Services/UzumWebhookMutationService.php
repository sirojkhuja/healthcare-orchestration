<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;
use App\Modules\Billing\Domain\Payments\PaymentActor;
use App\Modules\Integrations\Application\Contracts\PaymentWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\PaymentWebhookDeliveryData;
use App\Modules\Integrations\Application\Exceptions\UzumWebhookException;
use Carbon\CarbonImmutable;

final class UzumWebhookMutationService
{
    public function __construct(
        private readonly PaymentWorkflowService $paymentWorkflowService,
        private readonly PaymentWebhookDeliveryRepository $paymentWebhookDeliveryRepository,
        private readonly UzumPaymentResolver $uzumPaymentResolver,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function check(array $payload, string $authorization): array
    {
        $transId = $this->uzumPaymentResolver->transactionId($payload);
        $existing = $this->existingResponse('check', $transId);

        if ($existing !== null) {
            return $existing;
        }

        $payment = $this->uzumPaymentResolver->paymentByPayload($payload);
        $this->uzumPaymentResolver->assertAmountMatches($payment, $payload);

        if (in_array($payment->status, ['failed', 'canceled', 'refunded'], true)) {
            throw new UzumWebhookException('PAYMENT_STATE_INVALID', 'The payment is not eligible for Uzum processing.');
        }

        $response = $this->successResponse('CHECKED', $transId, $payment);
        /** @var array<string, mixed> $response */
        $this->storeDelivery('check', $payload, $authorization, $payment, $response, 'processed');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function confirm(array $payload, string $authorization): array
    {
        $transId = $this->uzumPaymentResolver->transactionId($payload);
        $existing = $this->existingResponse('confirm', $transId);

        if ($existing !== null) {
            return $existing;
        }

        $payment = $this->uzumPaymentResolver->paymentByTransactionOrPayload($payload);
        $this->assertTransactionConsistency($payment, $transId);

        $updated = match ($payment->status) {
            'captured' => $payment,
            'pending' => $this->paymentWorkflowService->capture(
                paymentId: $payment->paymentId,
                providerPaymentId: $transId,
                providerStatus: 'CONFIRMED',
                actor: $this->systemActor(),
                metadata: $this->metadata('confirm', $transId),
                tenantId: $payment->tenantId,
            ),
            default => throw new UzumWebhookException('PAYMENT_STATE_INVALID', 'The payment cannot be confirmed in the current state.'),
        };

        $response = $this->successResponse('CONFIRMED', $transId, $updated);
        /** @var array<string, mixed> $response */
        $this->storeDelivery('confirm', $payload, $authorization, $updated, $response, 'processed');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, string $authorization): array
    {
        $transId = $this->uzumPaymentResolver->transactionId($payload);
        $existing = $this->existingResponse('create', $transId);

        if ($existing !== null) {
            return $existing;
        }

        $payment = $this->uzumPaymentResolver->paymentByPayload($payload);
        $this->uzumPaymentResolver->assertAmountMatches($payment, $payload);
        $this->assertTransactionConsistency($payment, $transId, allowUnassigned: true);

        $updated = match ($payment->status) {
            'initiated' => $this->paymentWorkflowService->markPending(
                paymentId: $payment->paymentId,
                providerPaymentId: $transId,
                providerStatus: 'CREATED',
                actor: $this->systemActor(),
                metadata: $this->metadata('create', $transId),
                tenantId: $payment->tenantId,
            ),
            'pending' => $payment,
            default => throw new UzumWebhookException('PAYMENT_STATE_INVALID', 'The payment cannot be created in the current state.'),
        };

        $response = $this->successResponse('CREATED', $transId, $updated);
        /** @var array<string, mixed> $response */
        $this->storeDelivery('create', $payload, $authorization, $updated, $response, 'processed');

        return $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function existingResponse(string $method, string $transId): ?array
    {
        $existing = $this->paymentWebhookDeliveryRepository->findByReplayKey('uzum', $method, $transId);

        return $existing instanceof PaymentWebhookDeliveryData && is_array($existing->response)
            ? $existing->response
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function reverse(array $payload, string $authorization): array
    {
        $transId = $this->uzumPaymentResolver->transactionId($payload);
        $existing = $this->existingResponse('reverse', $transId);

        if ($existing !== null) {
            return $existing;
        }

        $payment = $this->uzumPaymentResolver->paymentByTransactionOrPayload($payload);
        $this->assertTransactionConsistency($payment, $transId);
        $updated = match ($payment->status) {
            'captured' => $this->paymentWorkflowService->refund(
                paymentId: $payment->paymentId,
                supportsRefunds: true,
                reason: 'Uzum reversed the confirmed payment.',
                providerPaymentId: $transId,
                providerStatus: 'REFUNDED',
                actor: $this->systemActor(),
                metadata: $this->metadata('reverse', $transId),
                tenantId: $payment->tenantId,
            ),
            'initiated', 'pending' => $this->paymentWorkflowService->cancel(
                paymentId: $payment->paymentId,
                reason: 'Uzum reversed the payment before confirmation.',
                providerPaymentId: $transId,
                providerStatus: 'CANCELED',
                actor: $this->systemActor(),
                metadata: $this->metadata('reverse', $transId),
                tenantId: $payment->tenantId,
            ),
            'canceled', 'failed', 'refunded' => $payment,
            default => throw new UzumWebhookException('PAYMENT_STATE_INVALID', 'The payment cannot be reversed in the current state.'),
        };

        $response = $this->successResponse($this->providerState($updated), $transId, $updated);
        /** @var array<string, mixed> $response */
        $this->storeDelivery('reverse', $payload, $authorization, $updated, $response, 'processed');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    public function storeFailure(
        string $method,
        array $payload,
        string $authorization,
        array $response,
        ?PaymentData $payment = null,
    ): void {
        $transId = isset($payload['transId']) && is_string($payload['transId']) ? trim($payload['transId']) : null;
        /** @var mixed $errorCandidate */
        $errorCandidate = $response['error'] ?? null;
        $error = is_array($errorCandidate)
            ? $this->normalizeAssocArray($errorCandidate)
            : [];

        if (
            $transId !== null
            && $transId !== ''
            && $this->paymentWebhookDeliveryRepository->findByReplayKey('uzum', $method, $transId) instanceof PaymentWebhookDeliveryData
        ) {
            return;
        }

        $delivery = $this->paymentWebhookDeliveryRepository->create([
            'provider_key' => 'uzum',
            'method' => $method,
            'replay_key' => $transId,
            'provider_transaction_id' => $transId,
            'request_id' => $transId,
            'payment_id' => $payment?->paymentId,
            'resolved_tenant_id' => $payment?->tenantId,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'auth_hash' => hash('sha256', trim($authorization)),
            'provider_time_millis' => $this->uzumPaymentResolver->providerTimeMillis($payload),
            'outcome' => 'rejected',
            'provider_error_code' => $this->nullableString($error['code'] ?? null) ?? 'PROCESSING_FAILED',
            'provider_error_message' => $this->nullableString($error['message'] ?? null) ?? 'The Uzum webhook request failed.',
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
                'provider_key' => 'uzum',
                'provider_transaction_id' => $transId,
                'source' => 'webhook',
            ],
            tenantId: $payment?->tenantId,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function status(array $payload, string $authorization): array
    {
        $transId = $this->uzumPaymentResolver->transactionId($payload);
        $existing = $this->existingResponse('status', $transId);

        if ($existing !== null) {
            return $existing;
        }

        $payment = $this->uzumPaymentResolver->paymentByTransactionOrPayload($payload);
        $this->assertTransactionConsistency($payment, $transId);
        $response = $this->successResponse($this->providerState($payment), $transId, $payment);
        /** @var array<string, mixed> $response */
        $this->storeDelivery('status', $payload, $authorization, $payment, $response, 'processed');

        return $response;
    }

    private function assertTransactionConsistency(PaymentData $payment, string $transId, bool $allowUnassigned = false): void
    {
        if ($payment->providerPaymentId === null && $allowUnassigned) {
            return;
        }

        if ($payment->providerPaymentId !== null && $payment->providerPaymentId === $transId) {
            return;
        }

        throw new UzumWebhookException('TRANSACTION_CONFLICT', 'A different Uzum transaction is already linked to this payment.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    private function storeDelivery(
        string $method,
        array $payload,
        string $authorization,
        PaymentData $payment,
        array $response,
        string $outcome,
    ): void {
        $transId = $this->uzumPaymentResolver->transactionId($payload);

        if ($this->paymentWebhookDeliveryRepository->findByReplayKey('uzum', $method, $transId) instanceof PaymentWebhookDeliveryData) {
            return;
        }

        $delivery = $this->paymentWebhookDeliveryRepository->create([
            'provider_key' => 'uzum',
            'method' => $method,
            'replay_key' => $transId,
            'provider_transaction_id' => $transId,
            'request_id' => $transId,
            'payment_id' => $payment->paymentId,
            'resolved_tenant_id' => $payment->tenantId,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'auth_hash' => hash('sha256', trim($authorization)),
            'provider_time_millis' => $this->uzumPaymentResolver->providerTimeMillis($payload),
            'outcome' => $outcome,
            'provider_error_code' => null,
            'provider_error_message' => null,
            'processed_at' => CarbonImmutable::now(),
            'payload' => $payload,
            'response' => $response,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payment_webhooks.processed',
            objectType: 'payment_webhook_delivery',
            objectId: $delivery->deliveryRecordId,
            after: $delivery->toArray(),
            metadata: $this->metadata($method, $transId),
            tenantId: $payment->tenantId,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function successResponse(string $state, string $transId, PaymentData $payment): array
    {
        return [
            'status' => 'OK',
            'error' => null,
            'data' => [
                'transaction_id' => $transId,
                'payment_id' => $payment->paymentId,
                'state' => $state,
                'provider_status' => $payment->providerStatus,
                'account' => [
                    'payment_id' => $payment->paymentId,
                ],
                'amount' => [
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ],
            ],
        ];
    }

    private function providerState(PaymentData $payment): string
    {
        return match ($payment->status) {
            'captured' => 'CONFIRMED',
            'pending' => 'CREATED',
            'failed' => 'FAILED',
            'canceled' => 'CANCELED',
            'refunded' => 'REFUNDED',
            default => 'INITIATED',
        };
    }

    private function systemActor(): PaymentActor
    {
        return new PaymentActor(type: 'system', name: 'uzum-webhook');
    }

    /**
     * @return array<string, string>
     */
    private function metadata(string $method, string $transId): array
    {
        return [
            'method' => $method,
            'provider_key' => 'uzum',
            'provider_transaction_id' => $transId,
            'source' => 'webhook',
        ];
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(array $value): array
    {
        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
