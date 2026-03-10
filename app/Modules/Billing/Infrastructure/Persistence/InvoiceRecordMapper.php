<?php

namespace App\Modules\Billing\Infrastructure\Persistence;

use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Data\InvoiceItemData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use stdClass;

final class InvoiceRecordMapper
{
    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

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

    /**
     * @param  list<InvoiceItemData>  $items
     */
    public function toData(stdClass $row, array $items = []): InvoiceData
    {
        return new InvoiceData(
            invoiceId: $this->stringValue($row->invoice_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            invoiceNumber: $this->stringValue($row->invoice_number ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            patientDisplayName: $this->stringValue($row->patient_display_name ?? null),
            priceListId: $this->nullableString($row->price_list_id ?? null),
            priceListCode: $this->nullableString($row->price_list_code ?? null),
            priceListName: $this->nullableString($row->price_list_name ?? null),
            currency: $this->stringValue($row->currency ?? null),
            invoiceDate: $this->date($row->invoice_date ?? null) ?? CarbonImmutable::now(),
            dueOn: $this->date($row->due_on ?? null),
            notes: $this->nullableString($row->notes ?? null),
            status: $this->stringValue($row->status ?? null),
            subtotalAmount: $this->decimalString($row->subtotal_amount ?? null),
            totalAmount: $this->decimalString($row->total_amount ?? null),
            itemCount: $this->intValue($row->item_count ?? null, count($items)),
            issuedAt: $this->nullableDateTime($row->issued_at ?? null),
            finalizedAt: $this->nullableDateTime($row->finalized_at ?? null),
            voidedAt: $this->nullableDateTime($row->voided_at ?? null),
            voidReason: $this->nullableString($row->void_reason ?? null),
            lastTransition: $this->jsonArray($row->last_transition ?? null),
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
            items: $items,
        );
    }

    public function toItemData(stdClass $row): InvoiceItemData
    {
        return new InvoiceItemData(
            invoiceItemId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            invoiceId: $this->stringValue($row->invoice_id ?? null),
            serviceId: $this->stringValue($row->service_id ?? null),
            serviceCode: $this->stringValue($row->service_code ?? null),
            serviceName: $this->stringValue($row->service_name ?? null),
            serviceCategory: $this->nullableString($row->service_category ?? null),
            serviceUnit: $this->nullableString($row->service_unit ?? null),
            description: $this->nullableString($row->description ?? null),
            quantity: $this->decimalString($row->quantity ?? null),
            unitPriceAmount: $this->decimalString($row->unit_price_amount ?? null),
            lineSubtotalAmount: $this->decimalString($row->line_subtotal_amount ?? null),
            currency: $this->stringValue($row->currency ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function intValue(mixed $value, int $fallback): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $fallback;
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
