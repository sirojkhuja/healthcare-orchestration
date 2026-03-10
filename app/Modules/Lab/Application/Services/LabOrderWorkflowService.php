<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Lab\Application\Contracts\LabOrderRepository;
use App\Modules\Lab\Application\Contracts\LabProviderGatewayRegistry;
use App\Modules\Lab\Application\Contracts\LabResultRepository;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabOrderSyncData;
use App\Modules\Lab\Application\Data\LabProviderOrderPayload;
use App\Modules\Lab\Application\Data\LabProviderResultPayload;
use App\Modules\Lab\Application\Data\LabResultData;
use App\Modules\Lab\Domain\LabOrders\InvalidLabOrderTransition;
use App\Modules\Lab\Domain\LabOrders\LabOrder;
use App\Modules\Lab\Domain\LabOrders\LabOrderActor;
use App\Modules\Lab\Domain\LabOrders\LabOrderStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LabOrderWorkflowService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LabOrderRepository $labOrderRepository,
        private readonly LabResultRepository $labResultRepository,
        private readonly LabOrderAggregateMapper $labOrderAggregateMapper,
        private readonly LabOrderActorContext $labOrderActorContext,
        private readonly LabProviderGatewayRegistry $labProviderGatewayRegistry,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly LabOutboxPublisher $labOutboxPublisher,
    ) {}

    public function cancel(string $orderId, string $reason): LabOrderData
    {
        return $this->transition(
            orderId: $orderId,
            auditAction: 'lab_orders.canceled',
            eventType: 'lab_order.canceled',
            mutator: static function (LabOrder $order, CarbonImmutable $occurredAt, LabOrderActor $actor) use ($reason): void {
                $order->cancel($occurredAt->toDateTimeImmutable(), $actor, $reason);
            },
        );
    }

    public function complete(string $orderId): LabOrderData
    {
        return $this->transition(
            orderId: $orderId,
            auditAction: 'lab_orders.completed',
            eventType: 'lab_order.completed',
            mutator: static function (LabOrder $order, CarbonImmutable $occurredAt, LabOrderActor $actor): void {
                $order->complete($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function markSpecimenCollected(string $orderId): LabOrderData
    {
        return $this->transition(
            orderId: $orderId,
            auditAction: 'lab_orders.specimen_collected',
            eventType: 'lab_order.specimen_collected',
            mutator: static function (LabOrder $order, CarbonImmutable $occurredAt, LabOrderActor $actor): void {
                $order->markSpecimenCollected($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function markSpecimenReceived(string $orderId): LabOrderData
    {
        return $this->transition(
            orderId: $orderId,
            auditAction: 'lab_orders.specimen_received',
            eventType: 'lab_order.specimen_received',
            mutator: static function (LabOrder $order, CarbonImmutable $occurredAt, LabOrderActor $actor): void {
                $order->markSpecimenReceived($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function send(string $orderId): LabOrderData
    {
        $before = $this->orderOrFail($this->tenantContext->requireTenantId(), $orderId);
        $gateway = $this->labProviderGatewayRegistry->resolve($before->labProviderKey);
        $dispatch = $gateway->sendOrder($before);

        return $this->transition(
            orderId: $orderId,
            auditAction: 'lab_orders.sent',
            eventType: 'lab_order.sent',
            mutator: static function (LabOrder $order, CarbonImmutable $occurredAt, LabOrderActor $actor) use ($dispatch): void {
                $order->send(
                    occurredAt: $dispatch->occurredAt->toDateTimeImmutable(),
                    actor: $actor,
                    externalOrderId: $dispatch->externalOrderId,
                );
            },
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function synchronizeRemoteSnapshot(
        LabOrderData $order,
        LabProviderOrderPayload $snapshot,
        LabOrderActor $actor,
        array $metadata = [],
    ): LabOrderSyncData {
        $tenantId = $order->tenantId;
        $processedResults = [];
        $resultCount = 0;

        /** @var array{order: LabOrderData, results: list<LabResultData>, result_count: int} $result */
        $result = DB::transaction(function () use (
            $tenantId,
            $order,
            $snapshot,
            $actor,
            $metadata,
            &$processedResults,
            &$resultCount,
        ): array {
            $current = $this->orderOrFail($tenantId, $order->orderId);
            $current = $this->applyRemoteStatus($current, $snapshot, $actor, $metadata);

            foreach ($snapshot->results as $resultPayload) {
                $processedResult = $this->upsertResult($current, $resultPayload, $metadata);
                $processedResults[] = $processedResult;
                $resultCount++;
            }

            return [
                'order' => $this->orderOrFail($tenantId, $order->orderId),
                'results' => $processedResults,
                'result_count' => $resultCount,
            ];
        });

        return new LabOrderSyncData(
            order: $result['order'],
            results: $result['results'],
            resultCount: $result['result_count'],
        );
    }

    /**
     * @param  callable(LabOrder, CarbonImmutable, LabOrderActor): void  $mutator
     */
    private function transition(string $orderId, string $auditAction, string $eventType, callable $mutator): LabOrderData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->orderOrFail($tenantId, $orderId);
        $actor = $this->labOrderActorContext->current();
        $occurredAt = CarbonImmutable::now();

        /** @var LabOrderData $updated */
        $updated = DB::transaction(function () use ($tenantId, $before, $actor, $occurredAt, $mutator): LabOrderData {
            $aggregate = $this->labOrderAggregateMapper->fromData($before);

            try {
                $mutator($aggregate, $occurredAt, $actor);
            } catch (InvalidLabOrderTransition $exception) {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

            return $this->persistAggregate($tenantId, $before, $aggregate);
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'lab_order',
            objectId: $updated->orderId,
            before: $before->toArray(),
            after: $updated->toArray(),
        ));
        $this->labOutboxPublisher->publishOrderEvent($eventType, $updated);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applyRemoteStatus(
        LabOrderData $order,
        LabProviderOrderPayload $snapshot,
        LabOrderActor $actor,
        array $metadata,
    ): LabOrderData {
        $targetStatus = LabOrderStatus::from($snapshot->status);
        $currentStatus = LabOrderStatus::from($order->status);

        if ($currentStatus === $targetStatus || $currentStatus->isTerminal()) {
            return $order;
        }

        if ($targetStatus === LabOrderStatus::CANCELED) {
            if ($currentStatus === LabOrderStatus::COMPLETED) {
                return $order;
            }

            return $this->applyRemoteStep(
                $order,
                'lab_orders.canceled',
                'lab_order.canceled',
                $actor,
                $metadata,
                static function (LabOrder $aggregate, CarbonImmutable $occurredAt, LabOrderActor $stepActor): void {
                    $aggregate->cancel(
                        occurredAt: $occurredAt->toDateTimeImmutable(),
                        actor: $stepActor,
                        reason: 'Provider synchronization marked the order as canceled.',
                    );
                },
                $snapshot->occurredAt,
            );
        }

        /** @var list<LabOrderStatus> $sequence */
        $sequence = [
            LabOrderStatus::SENT,
            LabOrderStatus::SPECIMEN_COLLECTED,
            LabOrderStatus::SPECIMEN_RECEIVED,
            LabOrderStatus::COMPLETED,
        ];
        $currentIndex = array_search($currentStatus, $sequence, true);
        $targetIndex = array_search($targetStatus, $sequence, true);

        if ($targetIndex === false) {
            return $order;
        }

        $startIndex = $currentIndex === false ? 0 : $currentIndex + 1;

        /** @var list<LabOrderStatus> $steps */
        $steps = array_slice($sequence, $startIndex, ($targetIndex - $startIndex) + 1);

        foreach ($steps as $status) {

            if ($status === LabOrderStatus::SENT) {
                $order = $this->applyRemoteStep(
                    $order,
                    'lab_orders.sent',
                    'lab_order.sent',
                    $actor,
                    $metadata,
                    static function (LabOrder $aggregate, CarbonImmutable $occurredAt, LabOrderActor $stepActor) use ($snapshot): void {
                        $aggregate->send(
                            occurredAt: $occurredAt->toDateTimeImmutable(),
                            actor: $stepActor,
                            externalOrderId: $snapshot->externalOrderId,
                        );
                    },
                    $snapshot->occurredAt,
                );

                continue;
            }

            if ($status === LabOrderStatus::SPECIMEN_COLLECTED) {
                $order = $this->applyRemoteStep(
                    $order,
                    'lab_orders.specimen_collected',
                    'lab_order.specimen_collected',
                    $actor,
                    $metadata,
                    static function (LabOrder $aggregate, CarbonImmutable $occurredAt, LabOrderActor $stepActor): void {
                        $aggregate->markSpecimenCollected($occurredAt->toDateTimeImmutable(), $stepActor);
                    },
                    $snapshot->occurredAt,
                );

                continue;
            }

            if ($status === LabOrderStatus::SPECIMEN_RECEIVED) {
                $order = $this->applyRemoteStep(
                    $order,
                    'lab_orders.specimen_received',
                    'lab_order.specimen_received',
                    $actor,
                    $metadata,
                    static function (LabOrder $aggregate, CarbonImmutable $occurredAt, LabOrderActor $stepActor): void {
                        $aggregate->markSpecimenReceived($occurredAt->toDateTimeImmutable(), $stepActor);
                    },
                    $snapshot->occurredAt,
                );

                continue;
            }

            $order = $this->applyRemoteStep(
                $order,
                'lab_orders.completed',
                'lab_order.completed',
                $actor,
                $metadata,
                static function (LabOrder $aggregate, CarbonImmutable $occurredAt, LabOrderActor $stepActor): void {
                    $aggregate->complete($occurredAt->toDateTimeImmutable(), $stepActor);
                },
                $snapshot->occurredAt,
            );
        }

        return $order;
    }

    /**
     * @param  callable(LabOrder, CarbonImmutable, LabOrderActor): void  $mutator
     * @param  array<string, mixed>  $metadata
     */
    private function applyRemoteStep(
        LabOrderData $before,
        string $auditAction,
        string $eventType,
        LabOrderActor $actor,
        array $metadata,
        callable $mutator,
        CarbonImmutable $occurredAt,
    ): LabOrderData {
        $aggregate = $this->labOrderAggregateMapper->fromData($before);

        try {
            $mutator($aggregate, $occurredAt, $actor);
        } catch (InvalidLabOrderTransition) {
            return $before;
        }

        $updated = $this->persistAggregate($before->tenantId, $before, $aggregate);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'lab_order',
            objectId: $updated->orderId,
            before: $before->toArray(),
            after: $updated->toArray(),
            metadata: $metadata,
            tenantId: $updated->tenantId,
        ));
        $this->labOutboxPublisher->publishOrderEvent($eventType, $updated, $metadata);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function upsertResult(
        LabOrderData $order,
        LabProviderResultPayload $payload,
        array $metadata,
    ): LabResultData {
        $tenantId = $order->tenantId;
        $existing = $payload->externalResultId !== null
            ? $this->labResultRepository->findInOrderByExternalId($tenantId, $order->orderId, $payload->externalResultId)
            : null;
        $attributes = [
            'lab_test_id' => $order->labTestId,
            'external_result_id' => $payload->externalResultId,
            'status' => $payload->status,
            'observed_at' => $payload->observedAt,
            'received_at' => $payload->receivedAt,
            'value_type' => $payload->valueType,
            'value_numeric' => $payload->valueNumeric,
            'value_text' => $payload->valueText,
            'value_boolean' => $payload->valueBoolean,
            'value_json' => $payload->valueJson,
            'unit' => $payload->unit,
            'reference_range' => $payload->referenceRange,
            'abnormal_flag' => $payload->abnormalFlag,
            'notes' => $payload->notes,
            'raw_payload' => $payload->rawPayload,
        ];

        if (! $existing instanceof LabResultData) {
            $created = $this->labResultRepository->create($tenantId, $order->orderId, $attributes);
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'lab_results.received',
                objectType: 'lab_result',
                objectId: $created->resultId,
                after: $created->toArray(),
                metadata: $metadata,
                tenantId: $tenantId,
            ));
            $this->labOutboxPublisher->publishResultReceived($order, $created);

            return $created;
        }

        $updates = [];

        foreach ([
            'lab_test_id' => $order->labTestId,
            'status' => $payload->status,
            'value_type' => $payload->valueType,
            'value_numeric' => $payload->valueNumeric,
            'value_text' => $payload->valueText,
            'value_boolean' => $payload->valueBoolean,
            'value_json' => $payload->valueJson,
            'unit' => $payload->unit,
            'reference_range' => $payload->referenceRange,
            'abnormal_flag' => $payload->abnormalFlag,
            'notes' => $payload->notes,
            'raw_payload' => $payload->rawPayload,
        ] as $key => $value) {
            $current = match ($key) {
                'lab_test_id' => $existing->labTestId,
                'status' => $existing->status,
                'value_type' => $existing->valueType,
                'value_numeric' => $existing->valueNumeric,
                'value_text' => $existing->valueText,
                'value_boolean' => $existing->valueBoolean,
                'value_json' => $existing->valueJson,
                'unit' => $existing->unit,
                'reference_range' => $existing->referenceRange,
                'abnormal_flag' => $existing->abnormalFlag,
                'notes' => $existing->notes,
                'raw_payload' => $existing->rawPayload,
            };

            if ($value !== $current) {
                $updates[$key] = $value;
            }
        }

        if (! $payload->observedAt->equalTo($existing->observedAt)) {
            $updates['observed_at'] = $payload->observedAt;
        }

        if (! $payload->receivedAt->equalTo($existing->receivedAt)) {
            $updates['received_at'] = $payload->receivedAt;
        }

        if ($updates === []) {
            return $existing;
        }

        $updated = $this->labResultRepository->update($tenantId, $order->orderId, $existing->resultId, $updates);

        if (! $updated instanceof LabResultData) {
            throw new \LogicException('Updated lab result could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_results.updated',
            objectType: 'lab_result',
            objectId: $updated->resultId,
            before: $existing->toArray(),
            after: $updated->toArray(),
            metadata: $metadata,
            tenantId: $tenantId,
        ));

        return $updated;
    }

    private function orderOrFail(string $tenantId, string $orderId): LabOrderData
    {
        $order = $this->labOrderRepository->findInTenant($tenantId, $orderId);

        if (! $order instanceof LabOrderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $order;
    }

    private function persistAggregate(string $tenantId, LabOrderData $before, LabOrder $aggregate): LabOrderData
    {
        $snapshot = $aggregate->snapshot();
        $updated = $this->labOrderRepository->update($tenantId, $before->orderId, [
            'status' => $snapshot['status'],
            'last_transition' => $snapshot['last_transition'],
            'external_order_id' => $snapshot['external_order_id'],
            'sent_at' => $snapshot['sent_at'],
            'specimen_collected_at' => $snapshot['specimen_collected_at'],
            'specimen_received_at' => $snapshot['specimen_received_at'],
            'completed_at' => $snapshot['completed_at'],
            'canceled_at' => $snapshot['canceled_at'],
            'cancel_reason' => $snapshot['cancel_reason'],
        ]);

        if (! $updated instanceof LabOrderData) {
            throw new \LogicException('Updated lab order could not be reloaded.');
        }

        return $updated;
    }
}
