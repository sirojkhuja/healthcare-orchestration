<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Domain\Invoices\Invoice;
use App\Modules\Billing\Domain\Invoices\InvoiceActor;
use App\Modules\Billing\Domain\Invoices\InvoiceStatus;
use App\Modules\Billing\Domain\Invoices\InvoiceTransitionData;
use Carbon\CarbonImmutable;

final class InvoiceAggregateMapper
{
    public function fromData(InvoiceData $invoice): Invoice
    {
        return Invoice::reconstitute(
            invoiceId: $invoice->invoiceId,
            tenantId: $invoice->tenantId,
            itemCount: $invoice->itemCount,
            totalAmount: $invoice->totalAmount,
            status: InvoiceStatus::from($invoice->status),
            lastTransition: $this->transitionData($invoice->lastTransition),
            issuedAt: $invoice->issuedAt?->toDateTimeImmutable(),
            finalizedAt: $invoice->finalizedAt?->toDateTimeImmutable(),
            voidedAt: $invoice->voidedAt?->toDateTimeImmutable(),
            voidReason: $invoice->voidReason,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function transitionData(?array $payload): ?InvoiceTransitionData
    {
        if ($payload === null) {
            return null;
        }

        $actor = $this->normalizeAssocArray($payload['actor'] ?? null);

        return new InvoiceTransitionData(
            fromStatus: InvoiceStatus::from($this->stringValue($payload, 'from_status', InvoiceStatus::DRAFT->value)),
            toStatus: InvoiceStatus::from($this->stringValue($payload, 'to_status', InvoiceStatus::DRAFT->value)),
            occurredAt: CarbonImmutable::parse($this->stringValue($payload, 'occurred_at', CarbonImmutable::now()->toIso8601String()))
                ->toDateTimeImmutable(),
            actor: new InvoiceActor(
                type: $this->stringValue($actor, 'type', 'user'),
                id: $this->nullableString($actor, 'id'),
                name: $this->nullableString($actor, 'name'),
            ),
            reason: $this->nullableString($payload, 'reason'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

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
     * @param  array<string, mixed>  $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        /** @psalm-suppress MixedAssignment */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
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
}
