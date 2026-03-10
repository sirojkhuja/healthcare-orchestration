<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Contracts\PriceListRepository;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InvoiceAttributeNormalizer
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly PriceListRepository $priceListRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizeCreate(array $attributes, string $tenantId, string $invoiceNumber): array
    {
        $invoiceDate = $this->dateString(
            $attributes['invoice_date'] ?? CarbonImmutable::now()->toDateString(),
            'invoice_date',
        );
        $dueOn = $this->nullableDateString($attributes['due_on'] ?? null, 'due_on');
        $this->assertDueDate($invoiceDate, $dueOn);
        $patient = $this->patientOrFail(
            $tenantId,
            $this->requiredString($attributes['patient_id'] ?? null, 'patient_id'),
        );
        $priceListId = $this->nullableString($attributes['price_list_id'] ?? null);
        $currencyInput = array_key_exists('currency', $attributes)
            ? $this->nullableCurrency($attributes['currency'])
            : null;
        $priceList = $priceListId !== null ? $this->priceListOrFail($tenantId, $priceListId) : null;
        $currency = $priceList instanceof PriceListData
            ? $this->currencyFromPriceList($priceList->currency, $currencyInput)
            : $this->standaloneCurrency($currencyInput);

        return [
            'invoice_number' => $invoiceNumber,
            'patient_id' => $patient->patientId,
            'patient_display_name' => $this->displayName($patient),
            'price_list_id' => $priceList instanceof PriceListData ? $priceList->priceListId : null,
            'price_list_code' => $priceList instanceof PriceListData ? $priceList->code : null,
            'price_list_name' => $priceList instanceof PriceListData ? $priceList->name : null,
            'currency' => $currency,
            'invoice_date' => $invoiceDate,
            'due_on' => $dueOn,
            'notes' => $this->nullableString($attributes['notes'] ?? null),
            'status' => 'draft',
            'subtotal_amount' => '0.00',
            'total_amount' => '0.00',
            'issued_at' => null,
            'finalized_at' => null,
            'voided_at' => null,
            'void_reason' => null,
            'last_transition' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(InvoiceData $invoice, array $attributes): array
    {
        $itemMutationRequested = array_key_exists('price_list_id', $attributes) || array_key_exists('currency', $attributes);

        if ($invoice->itemCount > 0 && $itemMutationRequested) {
            throw new ConflictHttpException('Invoice currency and price list cannot change once items exist.');
        }

        $patientId = array_key_exists('patient_id', $attributes)
            ? $this->requiredString($attributes['patient_id'], 'patient_id')
            : $invoice->patientId;
        $patient = $patientId !== $invoice->patientId
            ? $this->patientOrFail($invoice->tenantId, $patientId)
            : null;
        $currentPriceListId = $invoice->priceListId;
        $priceListId = array_key_exists('price_list_id', $attributes)
            ? $this->nullableString($attributes['price_list_id'])
            : $currentPriceListId;
        $currencyInput = array_key_exists('currency', $attributes)
            ? $this->nullableCurrency($attributes['currency'])
            : null;

        if ($priceListId === null && array_key_exists('price_list_id', $attributes) && $currencyInput === null) {
            throw new UnprocessableEntityHttpException('Clearing price_list_id requires an explicit currency field.');
        }

        $priceList = null;

        if ($priceListId !== null) {
            if ($priceListId !== $currentPriceListId || array_key_exists('currency', $attributes)) {
                $priceList = $this->priceListOrFail($invoice->tenantId, $priceListId);
            }
        }

        $invoiceDate = array_key_exists('invoice_date', $attributes)
            ? $this->dateString($attributes['invoice_date'], 'invoice_date')
            : $invoice->invoiceDate->toDateString();
        $dueOn = array_key_exists('due_on', $attributes)
            ? $this->nullableDateString($attributes['due_on'], 'due_on')
            : $invoice->dueOn?->toDateString();
        $this->assertDueDate($invoiceDate, $dueOn);
        $currency = $priceListId !== null
            ? $this->currencyFromPriceList(
                $priceList instanceof PriceListData ? $priceList->currency : $invoice->currency,
                $currencyInput,
            )
            : $this->standaloneCurrency($currencyInput ?? $invoice->currency);

        $candidate = [
            'patient_id' => $patientId,
            'patient_display_name' => $patient instanceof PatientData
                ? $this->displayName($patient)
                : $invoice->patientDisplayName,
            'price_list_id' => $priceListId,
            'price_list_code' => $priceList instanceof PriceListData
                ? $priceList->code
                : ($priceListId === null ? null : $invoice->priceListCode),
            'price_list_name' => $priceList instanceof PriceListData
                ? $priceList->name
                : ($priceListId === null ? null : $invoice->priceListName),
            'currency' => $currency,
            'invoice_date' => $invoiceDate,
            'due_on' => $dueOn,
            'notes' => array_key_exists('notes', $attributes)
                ? $this->nullableString($attributes['notes'])
                : $invoice->notes,
        ];

        return $this->diff($invoice, $candidate);
    }

    private function assertDueDate(string $invoiceDate, ?string $dueOn): void
    {
        if ($dueOn !== null && $dueOn < $invoiceDate) {
            throw new UnprocessableEntityHttpException('The due_on field must be on or after invoice_date.');
        }
    }

    private function currencyFromPriceList(string $priceListCurrency, ?string $currencyInput): string
    {
        if ($currencyInput !== null && $currencyInput !== $priceListCurrency) {
            throw new UnprocessableEntityHttpException('The currency field must match the selected price list currency.');
        }

        return $priceListCurrency;
    }

    private function standaloneCurrency(?string $currencyInput): string
    {
        if ($currencyInput === null) {
            throw new UnprocessableEntityHttpException('The currency field is required when price_list_id is omitted.');
        }

        return $currencyInput;
    }

    private function dateString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required and must use YYYY-MM-DD.', $field));
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', trim($value));

        if (! $date instanceof CarbonImmutable) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must use YYYY-MM-DD.', $field));
        }

        return $date->toDateString();
    }

    /**
     * @param  array<string, string|null>  $candidate
     * @return array<string, string|null>
     */
    private function diff(InvoiceData $invoice, array $candidate): array
    {
        /** @var array<string, string|null> $updates */
        $updates = [];
        /** @var array<string, string|null> $current */
        $current = [
            'patient_id' => $invoice->patientId,
            'patient_display_name' => $invoice->patientDisplayName,
            'price_list_id' => $invoice->priceListId,
            'price_list_code' => $invoice->priceListCode,
            'price_list_name' => $invoice->priceListName,
            'currency' => $invoice->currency,
            'invoice_date' => $invoice->invoiceDate->toDateString(),
            'due_on' => $invoice->dueOn?->toDateString(),
            'notes' => $invoice->notes,
        ];

        foreach ($candidate as $key => $value) {
            if ($value !== $current[$key]) {
                $updates[$key] = $value;
            }
        }

        return $updates;
    }

    private function displayName(PatientData $patient): string
    {
        $parts = array_values(array_filter([
            $patient->preferredName ?? $patient->firstName,
            $patient->lastName,
        ]));

        return $parts !== [] ? implode(' ', $parts) : 'Unknown patient';
    }

    private function nullableCurrency(mixed $value): ?string
    {
        $currency = $this->nullableString($value);

        if ($currency === null) {
            return null;
        }

        if (! preg_match('/^[A-Za-z]{3}$/', $currency)) {
            throw new UnprocessableEntityHttpException('The currency field must be a three-letter code.');
        }

        return strtoupper($currency);
    }

    private function nullableDateString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->dateString($value, $field);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function patientOrFail(string $tenantId, string $patientId): PatientData
    {
        $patient = $this->patientRepository->findInTenant($tenantId, $patientId);

        if (! $patient instanceof PatientData) {
            throw new UnprocessableEntityHttpException('The patient_id field must reference an active patient in the current tenant.');
        }

        return $patient;
    }

    private function priceListOrFail(string $tenantId, string $priceListId): PriceListData
    {
        $priceList = $this->priceListRepository->findInTenant($tenantId, $priceListId);

        if (! $priceList instanceof PriceListData) {
            throw new UnprocessableEntityHttpException('The price_list_id field must reference an existing price list in the current tenant.');
        }

        return $priceList;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $field));
        }

        return trim($value);
    }
}
