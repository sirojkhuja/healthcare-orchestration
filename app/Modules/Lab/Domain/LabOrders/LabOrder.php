<?php

namespace App\Modules\Lab\Domain\LabOrders;

use DateTimeImmutable;

final class LabOrder
{
    private LabOrderStatus $status;

    private ?LabOrderTransitionData $lastTransition = null;

    private ?string $externalOrderId = null;

    private ?DateTimeImmutable $sentAt = null;

    private ?DateTimeImmutable $specimenCollectedAt = null;

    private ?DateTimeImmutable $specimenReceivedAt = null;

    private ?DateTimeImmutable $completedAt = null;

    private ?DateTimeImmutable $canceledAt = null;

    private ?string $cancelReason = null;

    private function __construct(
        private readonly string $orderId,
        private readonly string $tenantId,
        LabOrderStatus $status,
    ) {
        $this->status = $status;
    }

    public static function reconstitute(
        string $orderId,
        string $tenantId,
        LabOrderStatus $status,
        ?LabOrderTransitionData $lastTransition = null,
        ?string $externalOrderId = null,
        ?DateTimeImmutable $sentAt = null,
        ?DateTimeImmutable $specimenCollectedAt = null,
        ?DateTimeImmutable $specimenReceivedAt = null,
        ?DateTimeImmutable $completedAt = null,
        ?DateTimeImmutable $canceledAt = null,
        ?string $cancelReason = null,
    ): self {
        $order = new self($orderId, $tenantId, $status);
        $order->lastTransition = $lastTransition;
        $order->externalOrderId = $externalOrderId;
        $order->sentAt = $sentAt;
        $order->specimenCollectedAt = $specimenCollectedAt;
        $order->specimenReceivedAt = $specimenReceivedAt;
        $order->completedAt = $completedAt;
        $order->canceledAt = $canceledAt;
        $order->cancelReason = $cancelReason;

        return $order;
    }

    public function cancel(DateTimeImmutable $occurredAt, LabOrderActor $actor, string $reason): void
    {
        LabOrderTransitionRules::assertCanCancel($this->status, $reason);
        $this->canceledAt = $occurredAt;
        $this->cancelReason = trim($reason);
        $this->applyTransition(LabOrderStatus::CANCELED, $occurredAt, $actor, $this->cancelReason);
    }

    public function complete(DateTimeImmutable $occurredAt, LabOrderActor $actor): void
    {
        LabOrderTransitionRules::assertCanComplete($this->status);
        $this->completedAt = $occurredAt;
        $this->applyTransition(LabOrderStatus::COMPLETED, $occurredAt, $actor);
    }

    public function markSpecimenCollected(DateTimeImmutable $occurredAt, LabOrderActor $actor): void
    {
        LabOrderTransitionRules::assertCanMarkSpecimenCollected($this->status);
        $this->specimenCollectedAt = $occurredAt;
        $this->applyTransition(LabOrderStatus::SPECIMEN_COLLECTED, $occurredAt, $actor);
    }

    public function markSpecimenReceived(DateTimeImmutable $occurredAt, LabOrderActor $actor): void
    {
        LabOrderTransitionRules::assertCanMarkSpecimenReceived($this->status);
        $this->specimenReceivedAt = $occurredAt;
        $this->applyTransition(LabOrderStatus::SPECIMEN_RECEIVED, $occurredAt, $actor);
    }

    public function send(DateTimeImmutable $occurredAt, LabOrderActor $actor, string $externalOrderId): void
    {
        LabOrderTransitionRules::assertCanSend($this->status);
        $this->externalOrderId = $externalOrderId;
        $this->sentAt = $occurredAt;
        $this->applyTransition(LabOrderStatus::SENT, $occurredAt, $actor);
    }

    /**
     * @return array{
     *     order_id: string,
     *     tenant_id: string,
     *     status: string,
     *     last_transition: array{
     *         from_status: string,
     *         to_status: string,
     *         occurred_at: string,
     *         actor: array{type: string, id: string|null, name: string|null},
     *         reason: string|null
     *     }|null,
     *     external_order_id: string|null,
     *     sent_at: string|null,
     *     specimen_collected_at: string|null,
     *     specimen_received_at: string|null,
     *     completed_at: string|null,
     *     canceled_at: string|null,
     *     cancel_reason: string|null
     * }
     */
    public function snapshot(): array
    {
        return [
            'order_id' => $this->orderId,
            'tenant_id' => $this->tenantId,
            'status' => $this->status->value,
            'last_transition' => $this->lastTransition?->toArray(),
            'external_order_id' => $this->externalOrderId,
            'sent_at' => $this->sentAt?->format(DATE_ATOM),
            'specimen_collected_at' => $this->specimenCollectedAt?->format(DATE_ATOM),
            'specimen_received_at' => $this->specimenReceivedAt?->format(DATE_ATOM),
            'completed_at' => $this->completedAt?->format(DATE_ATOM),
            'canceled_at' => $this->canceledAt?->format(DATE_ATOM),
            'cancel_reason' => $this->cancelReason,
        ];
    }

    public function status(): LabOrderStatus
    {
        return $this->status;
    }

    private function applyTransition(
        LabOrderStatus $toStatus,
        DateTimeImmutable $occurredAt,
        LabOrderActor $actor,
        ?string $reason = null,
    ): void {
        $this->lastTransition = new LabOrderTransitionData(
            fromStatus: $this->status,
            toStatus: $toStatus,
            occurredAt: $occurredAt,
            actor: $actor,
            reason: $reason,
        );
        $this->status = $toStatus;
    }
}
