<?php

namespace App\Modules\Pharmacy\Domain\Prescriptions;

use DateTimeImmutable;

final class Prescription
{
    private PrescriptionStatus $status;

    private ?PrescriptionTransitionData $lastTransition = null;

    private ?DateTimeImmutable $issuedAt = null;

    private ?DateTimeImmutable $dispensedAt = null;

    private ?DateTimeImmutable $canceledAt = null;

    private ?string $cancelReason = null;

    private function __construct(
        private readonly string $prescriptionId,
        private readonly string $tenantId,
        PrescriptionStatus $status,
    ) {
        $this->status = $status;
    }

    public static function reconstitute(
        string $prescriptionId,
        string $tenantId,
        PrescriptionStatus $status,
        ?PrescriptionTransitionData $lastTransition = null,
        ?DateTimeImmutable $issuedAt = null,
        ?DateTimeImmutable $dispensedAt = null,
        ?DateTimeImmutable $canceledAt = null,
        ?string $cancelReason = null,
    ): self {
        $prescription = new self($prescriptionId, $tenantId, $status);
        $prescription->lastTransition = $lastTransition;
        $prescription->issuedAt = $issuedAt;
        $prescription->dispensedAt = $dispensedAt;
        $prescription->canceledAt = $canceledAt;
        $prescription->cancelReason = $cancelReason;

        return $prescription;
    }

    public function cancel(DateTimeImmutable $occurredAt, PrescriptionActor $actor, string $reason): void
    {
        PrescriptionTransitionRules::assertCanCancel($this->status, $reason);
        $this->canceledAt = $occurredAt;
        $this->cancelReason = trim($reason);
        $this->applyTransition(PrescriptionStatus::CANCELED, $occurredAt, $actor, $this->cancelReason);
    }

    public function dispense(DateTimeImmutable $occurredAt, PrescriptionActor $actor): void
    {
        PrescriptionTransitionRules::assertCanDispense($this->status);
        $this->dispensedAt = $occurredAt;
        $this->applyTransition(PrescriptionStatus::DISPENSED, $occurredAt, $actor);
    }

    public function issue(DateTimeImmutable $occurredAt, PrescriptionActor $actor): void
    {
        PrescriptionTransitionRules::assertCanIssue($this->status);
        $this->issuedAt = $occurredAt;
        $this->applyTransition(PrescriptionStatus::ISSUED, $occurredAt, $actor);
    }

    /**
     * @return array{
     *     prescription_id: string,
     *     tenant_id: string,
     *     status: string,
     *     last_transition: array{
     *         from_status: string,
     *         to_status: string,
     *         occurred_at: string,
     *         actor: array{type: string, id: string|null, name: string|null},
     *         reason: string|null
     *     }|null,
     *     issued_at: string|null,
     *     dispensed_at: string|null,
     *     canceled_at: string|null,
     *     cancel_reason: string|null
     * }
     */
    public function snapshot(): array
    {
        return [
            'prescription_id' => $this->prescriptionId,
            'tenant_id' => $this->tenantId,
            'status' => $this->status->value,
            'last_transition' => $this->lastTransition?->toArray(),
            'issued_at' => $this->issuedAt?->format(DATE_ATOM),
            'dispensed_at' => $this->dispensedAt?->format(DATE_ATOM),
            'canceled_at' => $this->canceledAt?->format(DATE_ATOM),
            'cancel_reason' => $this->cancelReason,
        ];
    }

    public function status(): PrescriptionStatus
    {
        return $this->status;
    }

    private function applyTransition(
        PrescriptionStatus $toStatus,
        DateTimeImmutable $occurredAt,
        PrescriptionActor $actor,
        ?string $reason = null,
    ): void {
        $this->lastTransition = new PrescriptionTransitionData(
            fromStatus: $this->status,
            toStatus: $toStatus,
            occurredAt: $occurredAt,
            actor: $actor,
            reason: $reason,
        );
        $this->status = $toStatus;
    }
}
