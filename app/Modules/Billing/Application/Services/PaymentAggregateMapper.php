<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Domain\Payments\Payment;
use App\Modules\Billing\Domain\Payments\PaymentActor;
use App\Modules\Billing\Domain\Payments\PaymentStatus;
use App\Modules\Billing\Domain\Payments\PaymentTransitionData;
use Carbon\CarbonImmutable;

final class PaymentAggregateMapper
{
    public function fromData(PaymentData $payment): Payment
    {
        return Payment::reconstitute(
            paymentId: $payment->paymentId,
            tenantId: $payment->tenantId,
            initiatedAt: $payment->initiatedAt->toDateTimeImmutable(),
            status: PaymentStatus::from($payment->status),
            lastTransition: $this->transitionData($payment->lastTransition),
            pendingAt: $payment->pendingAt?->toDateTimeImmutable(),
            capturedAt: $payment->capturedAt?->toDateTimeImmutable(),
            failedAt: $payment->failedAt?->toDateTimeImmutable(),
            canceledAt: $payment->canceledAt?->toDateTimeImmutable(),
            refundedAt: $payment->refundedAt?->toDateTimeImmutable(),
            failureCode: $payment->failureCode,
            failureMessage: $payment->failureMessage,
            cancelReason: $payment->cancelReason,
            refundReason: $payment->refundReason,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function transitionData(?array $payload): ?PaymentTransitionData
    {
        if ($payload === null) {
            return null;
        }

        $actor = $this->normalizeAssocArray($payload['actor'] ?? null);

        return new PaymentTransitionData(
            fromStatus: PaymentStatus::from($this->stringValue($payload, 'from_status', PaymentStatus::INITIATED->value)),
            toStatus: PaymentStatus::from($this->stringValue($payload, 'to_status', PaymentStatus::INITIATED->value)),
            occurredAt: CarbonImmutable::parse($this->stringValue($payload, 'occurred_at', CarbonImmutable::now()->toIso8601String()))
                ->toDateTimeImmutable(),
            actor: new PaymentActor(
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
