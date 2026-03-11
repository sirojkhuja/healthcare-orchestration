<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Services\PaymentWorkflowService;
use App\Modules\Billing\Domain\Payments\PaymentActor;
use App\Modules\Integrations\Application\Contracts\PaymentWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\PaymentWebhookDeliveryData;
use App\Modules\Integrations\Application\Exceptions\PaymeJsonRpcException;
use Carbon\CarbonImmutable;

final class PaymeWebhookMutationService
{
    public function __construct(
        private readonly PaymentWorkflowService $paymentWorkflowService,
        private readonly PaymentWebhookDeliveryRepository $paymentWebhookDeliveryRepository,
        private readonly PaymePaymentResolver $paymePaymentResolver,
        private readonly PaymeTransactionViewBuilder $paymeTransactionViewBuilder,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array{id: mixed, method: string, params: array<string, mixed>}  $request
     * @return array{cancel_time: int, transaction: string, state: int}
     */
    public function cancelTransaction(array $request, string $authorization): array
    {
        $providerTransactionId = $this->paymePaymentResolver->providerTransactionId($request['params']);
        $payment = $this->paymePaymentResolver->paymentByProviderTransactionId($providerTransactionId);

        if (in_array($payment->status, ['canceled', 'refunded'], true)) {
            return $this->paymeTransactionViewBuilder->buildCancelResult($payment);
        }

        $reason = $this->paymePaymentResolver->requiredInt($request['params']['reason'] ?? null, 'reason');
        $updated = match ($payment->status) {
            'captured' => $this->paymentWorkflowService->refund(
                paymentId: $payment->paymentId,
                supportsRefunds: true,
                reason: sprintf('Payme canceled a performed transaction (reason %d).', $reason),
                providerStatus: '-2',
                actor: $this->systemActor(),
                metadata: $this->metadata('CancelTransaction', $providerTransactionId),
                tenantId: $payment->tenantId,
            ),
            'pending' => $this->paymentWorkflowService->cancel(
                paymentId: $payment->paymentId,
                reason: sprintf('Payme canceled the transaction (reason %d).', $reason),
                providerStatus: '-1',
                actor: $this->systemActor(),
                metadata: $this->metadata('CancelTransaction', $providerTransactionId),
                tenantId: $payment->tenantId,
            ),
            default => throw new PaymeJsonRpcException(-31008, 'The transaction cannot be canceled in the current state.'),
        };

        $response = $this->paymeTransactionViewBuilder->buildCancelResult($updated);
        $this->storeDelivery($request, $authorization, $providerTransactionId, $updated, null, $response);

        return $response;
    }

    /**
     * @param  array{id: mixed, method: string, params: array<string, mixed>}  $request
     * @return array{create_time: int, transaction: string, state: int}
     */
    public function createTransaction(array $request, string $authorization): array
    {
        $providerTransactionId = $this->paymePaymentResolver->providerTransactionId($request['params']);
        $existing = $this->paymentWebhookDeliveryRepository->findByReplayKey('payme', 'CreateTransaction', $providerTransactionId);

        if ($existing instanceof PaymentWebhookDeliveryData) {
            return $this->paymeTransactionViewBuilder->buildCreateResult(
                $this->paymePaymentResolver->paymentByProviderTransactionId($providerTransactionId),
            );
        }

        $payment = $this->paymePaymentResolver->paymentByAccount($request['params']);
        $this->paymePaymentResolver->assertAmountMatches($payment, $request['params']);

        if ($payment->providerPaymentId !== null && $payment->providerPaymentId !== $providerTransactionId) {
            throw new PaymeJsonRpcException(-31008, 'Another Payme transaction is already linked to this payment.');
        }

        if ($payment->status !== 'initiated' && $payment->providerPaymentId !== $providerTransactionId) {
            throw new PaymeJsonRpcException(-31008, 'The transaction cannot be created in the current state.');
        }

        $updated = $payment->providerPaymentId === $providerTransactionId
            ? $payment
            : $this->paymentWorkflowService->markPending(
                paymentId: $payment->paymentId,
                providerPaymentId: $providerTransactionId,
                providerStatus: '1',
                actor: $this->systemActor(),
                metadata: $this->metadata('CreateTransaction', $providerTransactionId),
                tenantId: $payment->tenantId,
            );

        $response = $this->paymeTransactionViewBuilder->buildCreateResult($updated);
        $this->storeDelivery(
            $request,
            $authorization,
            $providerTransactionId,
            $updated,
            $this->paymePaymentResolver->requiredInt($request['params']['time'] ?? null, 'time'),
            $response,
        );

        return $response;
    }

    /**
     * @param  array{id: mixed, method: string, params: array<string, mixed>}  $request
     * @return array{perform_time: int, transaction: string, state: int}
     */
    public function performTransaction(array $request, string $authorization): array
    {
        $providerTransactionId = $this->paymePaymentResolver->providerTransactionId($request['params']);
        $payment = $this->paymePaymentResolver->paymentByProviderTransactionId($providerTransactionId);

        if ($payment->status === 'captured') {
            return $this->paymeTransactionViewBuilder->buildPerformResult($payment);
        }

        if ($payment->status !== 'pending') {
            throw new PaymeJsonRpcException(-31008, 'The transaction cannot be performed in the current state.');
        }

        $updated = $this->paymentWorkflowService->capture(
            paymentId: $payment->paymentId,
            providerStatus: '2',
            actor: $this->systemActor(),
            metadata: $this->metadata('PerformTransaction', $providerTransactionId),
            tenantId: $payment->tenantId,
        );

        $response = $this->paymeTransactionViewBuilder->buildPerformResult($updated);
        $this->storeDelivery($request, $authorization, $providerTransactionId, $updated, null, $response);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function metadata(string $method, string $providerTransactionId): array
    {
        return [
            'method' => $method,
            'provider_key' => 'payme',
            'provider_transaction_id' => $providerTransactionId,
            'source' => 'webhook',
        ];
    }

    /**
     * @param  array{id: mixed, method: string, params: array<string, mixed>}  $request
     * @param  array<string, mixed>  $response
     */
    private function storeDelivery(
        array $request,
        string $authorization,
        string $providerTransactionId,
        PaymentData $payment,
        ?int $providerTimeMillis,
        array $response,
    ): void {
        if ($this->paymentWebhookDeliveryRepository->findByReplayKey('payme', $request['method'], $providerTransactionId) instanceof PaymentWebhookDeliveryData) {
            return;
        }

        $delivery = $this->paymentWebhookDeliveryRepository->create([
            'provider_key' => 'payme',
            'method' => $request['method'],
            'replay_key' => $providerTransactionId,
            'provider_transaction_id' => $providerTransactionId,
            'request_id' => is_scalar($request['id']) ? (string) $request['id'] : null,
            'payment_id' => $payment->paymentId,
            'resolved_tenant_id' => $payment->tenantId,
            'payload_hash' => hash('sha256', json_encode($request, JSON_THROW_ON_ERROR)),
            'auth_hash' => hash('sha256', trim($authorization)),
            'provider_time_millis' => $providerTimeMillis,
            'outcome' => 'processed',
            'provider_error_code' => null,
            'provider_error_message' => null,
            'processed_at' => CarbonImmutable::now(),
            'payload' => [
                'id' => $request['id'],
                'method' => $request['method'],
                'params' => $request['params'],
            ],
            'response' => $response,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payment_webhooks.processed',
            objectType: 'payment_webhook_delivery',
            objectId: $delivery->deliveryRecordId,
            after: $delivery->toArray(),
            metadata: $this->metadata($request['method'], $providerTransactionId),
            tenantId: $payment->tenantId,
        ));
    }

    private function systemActor(): PaymentActor
    {
        return new PaymentActor(type: 'system', name: 'payme-webhook');
    }
}
