<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Domain\LabOrders\LabOrder;
use App\Modules\Lab\Domain\LabOrders\LabOrderActor;
use App\Modules\Lab\Domain\LabOrders\LabOrderStatus;
use App\Modules\Lab\Domain\LabOrders\LabOrderTransitionData;
use Carbon\CarbonImmutable;

final class LabOrderAggregateMapper
{
    public function fromData(LabOrderData $order): LabOrder
    {
        return LabOrder::reconstitute(
            orderId: $order->orderId,
            tenantId: $order->tenantId,
            status: LabOrderStatus::from($order->status),
            lastTransition: $this->transitionData($order->lastTransition),
            externalOrderId: $order->externalOrderId,
            sentAt: $order->sentAt?->toDateTimeImmutable(),
            specimenCollectedAt: $order->specimenCollectedAt?->toDateTimeImmutable(),
            specimenReceivedAt: $order->specimenReceivedAt?->toDateTimeImmutable(),
            completedAt: $order->completedAt?->toDateTimeImmutable(),
            canceledAt: $order->canceledAt?->toDateTimeImmutable(),
            cancelReason: $order->cancelReason,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function transitionData(?array $payload): ?LabOrderTransitionData
    {
        if ($payload === null) {
            return null;
        }

        $actor = $this->normalizeAssocArray($payload['actor'] ?? null);

        return new LabOrderTransitionData(
            fromStatus: LabOrderStatus::from($this->stringValue($payload, 'from_status', LabOrderStatus::DRAFT->value)),
            toStatus: LabOrderStatus::from($this->stringValue($payload, 'to_status', LabOrderStatus::DRAFT->value)),
            occurredAt: CarbonImmutable::parse($this->stringValue($payload, 'occurred_at', CarbonImmutable::now()->toIso8601String()))
                ->toDateTimeImmutable(),
            actor: new LabOrderActor(
                type: $this->stringValue($actor, 'type', 'user'),
                id: $this->nullableString($actor, 'id'),
                name: $this->nullableString($actor, 'name'),
            ),
            reason: $this->nullableString($payload, 'reason'),
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
    private function nullableString(array $payload, string $key): ?string
    {
        /** @psalm-suppress MixedAssignment */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        /** @var array<string, mixed> $normalized */
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
