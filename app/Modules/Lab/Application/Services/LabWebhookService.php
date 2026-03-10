<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Lab\Application\Contracts\LabOrderRepository;
use App\Modules\Lab\Application\Contracts\LabProviderGatewayRegistry;
use App\Modules\Lab\Application\Contracts\LabResultRepository;
use App\Modules\Lab\Application\Contracts\LabWebhookDeliveryRepository;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabProviderOrderPayload;
use App\Modules\Lab\Application\Data\LabProviderResultPayload;
use App\Modules\Lab\Application\Data\LabWebhookProcessResultData;
use App\Modules\Lab\Application\Data\LabWebhookVerificationData;
use App\Modules\Lab\Application\Exceptions\InvalidLabWebhookSignatureException;
use App\Modules\Lab\Domain\LabOrders\LabOrderActor;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LabWebhookService
{
    public function __construct(
        private readonly LabProviderGatewayRegistry $labProviderGatewayRegistry,
        private readonly LabOrderRepository $labOrderRepository,
        private readonly LabResultRepository $labResultRepository,
        private readonly LabWebhookDeliveryRepository $labWebhookDeliveryRepository,
        private readonly LabOrderWorkflowService $labOrderWorkflowService,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(string $providerKey, string $signature, string $rawPayload, array $payload): LabWebhookProcessResultData
    {
        $gateway = $this->labProviderGatewayRegistry->resolve($providerKey);

        if (! $gateway->verifyWebhookSignature($signature, $rawPayload)) {
            throw new InvalidLabWebhookSignatureException('Inbound webhook signature failed verification.');
        }

        $deliveryId = $this->stringValue($payload, 'delivery_id');
        $existingDelivery = $this->labWebhookDeliveryRepository->findByProviderAndDeliveryId($providerKey, $deliveryId);

        if ($existingDelivery !== null && $existingDelivery->labOrderId !== null && $existingDelivery->resolvedTenantId !== null) {
            $order = $this->labOrderRepository->findInTenant($existingDelivery->resolvedTenantId, $existingDelivery->labOrderId);

            if (! $order instanceof LabOrderData) {
                throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
            }

            return new LabWebhookProcessResultData(
                providerKey: $providerKey,
                deliveryId: $deliveryId,
                alreadyProcessed: true,
                order: $order,
                results: $this->labResultRepository->listForOrder($order->tenantId, $order->orderId),
                delivery: $existingDelivery,
            );
        }

        $externalOrderId = $this->stringValue($payload, 'external_order_id');
        $order = $this->labOrderRepository->findByExternalOrderId($providerKey, $externalOrderId);

        if (! $order instanceof LabOrderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $resultPayloads = [];

        foreach (is_array($payload['results'] ?? null) ? $payload['results'] : [] as $result) {
            if (! is_array($result)) {
                continue;
            }

            $resultPayloads[] = $this->resultPayload($this->normalizeAssocArray($result));
        }

        $snapshot = new LabProviderOrderPayload(
            externalOrderId: $externalOrderId,
            status: $this->stringValue($payload, 'status'),
            occurredAt: CarbonImmutable::parse($this->stringValue($payload, 'occurred_at', CarbonImmutable::now()->toIso8601String())),
            results: $resultPayloads,
        );
        $sync = $this->labOrderWorkflowService->synchronizeRemoteSnapshot(
            order: $order,
            snapshot: $snapshot,
            actor: new LabOrderActor(type: 'system', name: sprintf('lab-webhook/%s', $providerKey)),
            metadata: [
                'source' => 'webhook',
                'provider_key' => $providerKey,
                'delivery_id' => $deliveryId,
            ],
        );
        $delivery = $this->labWebhookDeliveryRepository->create([
            'provider_key' => $providerKey,
            'delivery_id' => $deliveryId,
            'payload_hash' => hash('sha256', $rawPayload),
            'signature_hash' => hash('sha256', trim($signature)),
            'lab_order_id' => $sync->order->orderId,
            'resolved_tenant_id' => $sync->order->tenantId,
            'outcome' => 'processed',
            'occurred_at' => $snapshot->occurredAt,
            'processed_at' => CarbonImmutable::now(),
            'error_message' => null,
            'payload' => $payload,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_webhooks.processed',
            objectType: 'lab_webhook_delivery',
            objectId: $delivery->deliveryRecordId,
            after: $delivery->toArray(),
            metadata: [
                'provider_key' => $providerKey,
                'delivery_id' => $deliveryId,
                'lab_order_id' => $sync->order->orderId,
                'result_count' => $sync->resultCount,
            ],
            tenantId: $sync->order->tenantId,
        ));

        return new LabWebhookProcessResultData(
            providerKey: $providerKey,
            deliveryId: $deliveryId,
            alreadyProcessed: false,
            order: $sync->order,
            results: $sync->results,
            delivery: $delivery,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(string $providerKey, string $signature, string $rawPayload, array $payload): LabWebhookVerificationData
    {
        $gateway = $this->labProviderGatewayRegistry->resolve($providerKey);

        return new LabWebhookVerificationData(
            providerKey: $providerKey,
            valid: $gateway->verifyWebhookSignature($signature, $rawPayload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resultPayload(array $payload): LabProviderResultPayload
    {
        $valueJson = $this->normalizeAssocArrayOrNull($payload['value_json'] ?? null);
        $rawPayload = $this->normalizeAssocArrayOrNull($payload['raw_payload'] ?? null) ?? $payload;

        return new LabProviderResultPayload(
            externalResultId: $this->nullableStringValue($payload, 'external_result_id'),
            status: $this->stringValue($payload, 'status', 'final'),
            observedAt: CarbonImmutable::parse($this->stringValue($payload, 'observed_at', CarbonImmutable::now()->toIso8601String())),
            receivedAt: CarbonImmutable::parse($this->stringValue($payload, 'received_at', CarbonImmutable::now()->toIso8601String())),
            valueType: $this->stringValue($payload, 'value_type', 'text'),
            valueNumeric: $this->nullableScalarString($payload['value_numeric'] ?? null),
            valueText: $this->nullableStringValue($payload, 'value_text'),
            valueBoolean: array_key_exists('value_boolean', $payload) ? (bool) $payload['value_boolean'] : null,
            valueJson: $valueJson,
            unit: $this->nullableStringValue($payload, 'unit'),
            referenceRange: $this->nullableStringValue($payload, 'reference_range'),
            abnormalFlag: $this->nullableStringValue($payload, 'abnormal_flag'),
            notes: $this->nullableStringValue($payload, 'notes'),
            rawPayload: $rawPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        /** @psalm-suppress MixedAssignment */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nullableStringValue(array $payload, string $key): ?string
    {
        /** @psalm-suppress MixedAssignment */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(array $payload): array
    {
        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeAssocArrayOrNull(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        return $this->normalizeAssocArray($payload);
    }

    private function nullableScalarString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
