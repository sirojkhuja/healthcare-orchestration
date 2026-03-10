<?php

namespace App\Modules\Billing\Domain\Invoices;

use DateTimeImmutable;

final class Invoice
{
    private InvoiceStatus $status;

    private ?InvoiceTransitionData $lastTransition = null;

    private ?DateTimeImmutable $issuedAt = null;

    private ?DateTimeImmutable $finalizedAt = null;

    private ?DateTimeImmutable $voidedAt = null;

    private ?string $voidReason = null;

    private function __construct(
        private readonly string $invoiceId,
        private readonly string $tenantId,
        private readonly int $itemCount,
        private readonly string $totalAmount,
        InvoiceStatus $status,
    ) {
        $this->status = $status;
    }

    public static function reconstitute(
        string $invoiceId,
        string $tenantId,
        int $itemCount,
        string $totalAmount,
        InvoiceStatus $status,
        ?InvoiceTransitionData $lastTransition = null,
        ?DateTimeImmutable $issuedAt = null,
        ?DateTimeImmutable $finalizedAt = null,
        ?DateTimeImmutable $voidedAt = null,
        ?string $voidReason = null,
    ): self {
        $invoice = new self($invoiceId, $tenantId, $itemCount, $totalAmount, $status);
        $invoice->lastTransition = $lastTransition;
        $invoice->issuedAt = $issuedAt;
        $invoice->finalizedAt = $finalizedAt;
        $invoice->voidedAt = $voidedAt;
        $invoice->voidReason = $voidReason;

        return $invoice;
    }

    public function finalize(DateTimeImmutable $occurredAt, InvoiceActor $actor): void
    {
        InvoiceTransitionRules::assertCanFinalize($this->status);
        $this->finalizedAt = $occurredAt;
        $this->applyTransition(InvoiceStatus::FINALIZED, $occurredAt, $actor);
    }

    public function issue(DateTimeImmutable $occurredAt, InvoiceActor $actor): void
    {
        InvoiceTransitionRules::assertCanIssue($this->status, $this->itemCount, $this->totalAmount);
        $this->issuedAt = $occurredAt;
        $this->applyTransition(InvoiceStatus::ISSUED, $occurredAt, $actor);
    }

    /**
     * @return array{
     *     invoice_id: string,
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
     *     finalized_at: string|null,
     *     voided_at: string|null,
     *     void_reason: string|null
     * }
     */
    public function snapshot(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'tenant_id' => $this->tenantId,
            'status' => $this->status->value,
            'last_transition' => $this->lastTransition?->toArray(),
            'issued_at' => $this->issuedAt?->format(DATE_ATOM),
            'finalized_at' => $this->finalizedAt?->format(DATE_ATOM),
            'voided_at' => $this->voidedAt?->format(DATE_ATOM),
            'void_reason' => $this->voidReason,
        ];
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    public function void(DateTimeImmutable $occurredAt, InvoiceActor $actor, string $reason): void
    {
        InvoiceTransitionRules::assertCanVoid($this->status, $reason);
        $this->voidedAt = $occurredAt;
        $this->voidReason = trim($reason);
        $this->applyTransition(InvoiceStatus::VOID, $occurredAt, $actor, $this->voidReason);
    }

    private function applyTransition(
        InvoiceStatus $toStatus,
        DateTimeImmutable $occurredAt,
        InvoiceActor $actor,
        ?string $reason = null,
    ): void {
        $this->lastTransition = new InvoiceTransitionData(
            fromStatus: $this->status,
            toStatus: $toStatus,
            occurredAt: $occurredAt,
            actor: $actor,
            reason: $reason,
        );
        $this->status = $toStatus;
    }
}
