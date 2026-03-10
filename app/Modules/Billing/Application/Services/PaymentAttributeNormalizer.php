<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Contracts\InvoiceRepository;
use App\Modules\Billing\Application\Data\InvoiceData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PaymentAttributeNormalizer
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizeInitiate(array $attributes, string $tenantId, CarbonImmutable $initiatedAt): array
    {
        $invoice = $this->invoiceOrFail(
            $tenantId,
            $this->requiredString($attributes['invoice_id'] ?? null, 'invoice_id'),
        );

        if (! in_array($invoice->status, ['issued', 'finalized'], true)) {
            throw new ConflictHttpException('Payments may be initiated only for issued or finalized invoices.');
        }

        $amount = $this->decimal($attributes['amount'] ?? null, 'amount');
        $invoiceTotalMinor = $this->minorUnits($invoice->totalAmount);

        if ($this->minorUnits($amount) > $invoiceTotalMinor) {
            throw new UnprocessableEntityHttpException('The amount field may not exceed the linked invoice total_amount.');
        }

        $currencyInput = array_key_exists('currency', $attributes)
            ? $this->currency($attributes['currency'])
            : null;
        $currency = $currencyInput ?? $invoice->currency;

        if ($currency !== $invoice->currency) {
            throw new UnprocessableEntityHttpException('The currency field must match the linked invoice currency.');
        }

        return [
            'invoice_id' => $invoice->invoiceId,
            'invoice_number' => $invoice->invoiceNumber,
            'provider_key' => $this->providerKey($attributes['provider_key'] ?? null),
            'amount' => $amount,
            'currency' => $currency,
            'description' => $this->nullableString($attributes['description'] ?? null),
            'status' => 'initiated',
            'provider_payment_id' => null,
            'provider_status' => null,
            'checkout_url' => null,
            'failure_code' => null,
            'failure_message' => null,
            'cancel_reason' => null,
            'refund_reason' => null,
            'last_transition' => null,
            'initiated_at' => $initiatedAt,
            'pending_at' => null,
            'captured_at' => null,
            'failed_at' => null,
            'canceled_at' => null,
            'refunded_at' => null,
        ];
    }

    private function currency(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException('The currency field must be a three-letter code.');
        }

        $currency = strtoupper(trim($value));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new UnprocessableEntityHttpException('The currency field must be a three-letter code.');
        }

        return $currency;
    }

    private function decimal(mixed $value, string $field): string
    {
        $normalized = match (true) {
            is_int($value) => sprintf('%d.00', $value),
            is_float($value) => number_format($value, 2, '.', ''),
            is_string($value) => trim($value),
            default => '',
        };

        if ($normalized === '' || ! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $normalized)) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be a positive decimal with up to two fraction digits.', $field));
        }

        $parts = explode('.', $normalized, 2);
        $amount = $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');

        if ((float) $amount <= 0.0) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be greater than zero.', $field));
        }

        return $amount;
    }

    private function invoiceOrFail(string $tenantId, string $invoiceId): InvoiceData
    {
        $invoice = $this->invoiceRepository->findInTenant($tenantId, $invoiceId);

        if (! $invoice instanceof InvoiceData) {
            throw new UnprocessableEntityHttpException('The invoice_id field must reference an existing invoice in the current tenant.');
        }

        return $invoice;
    }

    private function minorUnits(string $amount): int
    {
        $parts = explode('.', $amount, 2);
        $whole = $parts[0];
        $decimal = $parts[1] ?? '';

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function providerKey(mixed $value): string
    {
        $providerKey = strtolower($this->requiredString($value, 'provider_key'));

        if (! preg_match('/^[a-z0-9._-]+$/', $providerKey)) {
            throw new UnprocessableEntityHttpException('The provider_key field must use lowercase slug format.');
        }

        return $providerKey;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $field));
        }

        return trim($value);
    }
}
