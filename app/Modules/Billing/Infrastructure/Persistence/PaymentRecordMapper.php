<?php

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Modules\Billing\Application\Data\PaymentData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use stdClass;

final class PaymentRecordMapper
{
    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

    public function decimalString(mixed $value): string
    {
        if (is_int($value)) {
            return sprintf('%d.00', $value);
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        if (! is_string($value)) {
            return '0.00';
        }

        $parts = explode('.', $value, 2);
        $whole = $parts[0];
        $decimal = $parts[1] ?? '';

        return $whole.'.'.str_pad($decimal, 2, '0');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $this->stringKeyedArray($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $this->stringKeyedArray($decoded) : null;
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        return $value === null ? null : $this->dateTime($value);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    public function toData(stdClass $row): PaymentData
    {
        return new PaymentData(
            paymentId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            invoiceId: $this->stringValue($row->invoice_id ?? null),
            invoiceNumber: $this->stringValue($row->invoice_number ?? null),
            providerKey: $this->stringValue($row->provider_key ?? null),
            amount: $this->decimalString($row->amount ?? null),
            currency: $this->stringValue($row->currency ?? null),
            description: $this->nullableString($row->description ?? null),
            status: $this->stringValue($row->status ?? null),
            providerPaymentId: $this->nullableString($row->provider_payment_id ?? null),
            providerStatus: $this->nullableString($row->provider_status ?? null),
            checkoutUrl: $this->nullableString($row->checkout_url ?? null),
            failureCode: $this->nullableString($row->failure_code ?? null),
            failureMessage: $this->nullableString($row->failure_message ?? null),
            cancelReason: $this->nullableString($row->cancel_reason ?? null),
            refundReason: $this->nullableString($row->refund_reason ?? null),
            lastTransition: $this->jsonArray($row->last_transition ?? null),
            initiatedAt: $this->dateTime($row->initiated_at ?? null),
            pendingAt: $this->nullableDateTime($row->pending_at ?? null),
            capturedAt: $this->nullableDateTime($row->captured_at ?? null),
            failedAt: $this->nullableDateTime($row->failed_at ?? null),
            canceledAt: $this->nullableDateTime($row->canceled_at ?? null),
            refundedAt: $this->nullableDateTime($row->refunded_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    /**
     * @param  array<mixed, mixed>  $value
     * @return array<string, mixed>|null
     */
    private function stringKeyedArray(array $value): ?array
    {
        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                return null;
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
