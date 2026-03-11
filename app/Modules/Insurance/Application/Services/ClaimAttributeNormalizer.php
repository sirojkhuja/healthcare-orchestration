<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\Billing\Application\Contracts\InvoiceRepository;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Domain\Invoices\InvoiceStatus;
use App\Modules\Insurance\Application\Contracts\PatientInsurancePolicyRepository;
use App\Modules\Insurance\Application\Contracts\PayerRepository;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Data\PatientInsurancePolicyData;
use App\Modules\Insurance\Application\Data\PayerData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ClaimAttributeNormalizer
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly PayerRepository $payerRepository,
        private readonly PatientInsurancePolicyRepository $patientInsurancePolicyRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizeCreate(array $attributes, string $claimNumber): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $invoice = $this->invoiceOrFail($tenantId, $this->requiredString($attributes['invoice_id'] ?? null, 'invoice_id'));
        $this->assertInvoiceEligible($invoice);
        $payer = $this->payerOrFail($tenantId, $this->requiredString($attributes['payer_id'] ?? null, 'payer_id'));
        $this->assertPayerActive($payer);
        $policy = $this->resolvePolicy(
            tenantId: $tenantId,
            patientId: $invoice->patientId,
            payer: $payer,
            patientPolicyId: array_key_exists('patient_policy_id', $attributes)
                ? $this->nullableUuid($attributes['patient_policy_id'])
                : null,
        );
        $serviceDate = $this->nullableDate($attributes['service_date'] ?? null) ?? $invoice->invoiceDate;
        $billedAmount = $this->decimal($attributes['billed_amount'] ?? $invoice->totalAmount, 'billed_amount');
        $this->assertAmountDoesNotExceedInvoice($billedAmount, $invoice->totalAmount);

        return [
            'claim_number' => $claimNumber,
            'payer_id' => $payer->payerId,
            'payer_code' => $payer->code,
            'payer_name' => $payer->name,
            'payer_insurance_code' => $payer->insuranceCode,
            'patient_id' => $invoice->patientId,
            'patient_display_name' => $invoice->patientDisplayName,
            'invoice_id' => $invoice->invoiceId,
            'invoice_number' => $invoice->invoiceNumber,
            'patient_policy_id' => $policy?->policyId,
            'patient_policy_number' => $policy?->policyNumber,
            'patient_member_number' => $policy?->memberNumber,
            'patient_group_number' => $policy?->groupNumber,
            'patient_plan_name' => $policy?->planName,
            'currency' => $invoice->currency,
            'service_date' => $serviceDate->toDateString(),
            'billed_amount' => $billedAmount,
            'approved_amount' => null,
            'paid_amount' => null,
            'notes' => $this->nullableString($attributes['notes'] ?? null),
            'status' => 'draft',
            'attachment_count' => 0,
            'service_categories' => $this->serviceCategories($invoice),
            'submitted_at' => null,
            'review_started_at' => null,
            'approved_at' => null,
            'denied_at' => null,
            'paid_at' => null,
            'denial_reason' => null,
            'last_transition' => null,
            'adjudication_history' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(ClaimData $claim, array $attributes): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $invoice = $this->invoiceOrFail($tenantId, $claim->invoiceId);
        $updates = [];
        $payer = array_key_exists('payer_id', $attributes)
            ? $this->payerOrFail($tenantId, $this->requiredString($attributes['payer_id'], 'payer_id'))
            : $this->payerOrFail($tenantId, $claim->payerId);
        $this->assertPayerActive($payer);

        if (array_key_exists('payer_id', $attributes)) {
            $updates['payer_id'] = $payer->payerId;
            $updates['payer_code'] = $payer->code;
            $updates['payer_name'] = $payer->name;
            $updates['payer_insurance_code'] = $payer->insuranceCode;
        }

        if (array_key_exists('patient_policy_id', $attributes) || array_key_exists('payer_id', $attributes)) {
            $policy = $this->resolvePolicy(
                tenantId: $tenantId,
                patientId: $claim->patientId,
                payer: $payer,
                patientPolicyId: array_key_exists('patient_policy_id', $attributes)
                    ? $this->nullableUuid($attributes['patient_policy_id'])
                    : $claim->patientPolicyId,
            );
            $updates['patient_policy_id'] = $policy?->policyId;
            $updates['patient_policy_number'] = $policy?->policyNumber;
            $updates['patient_member_number'] = $policy?->memberNumber;
            $updates['patient_group_number'] = $policy?->groupNumber;
            $updates['patient_plan_name'] = $policy?->planName;
        }

        if (array_key_exists('service_date', $attributes)) {
            $updates['service_date'] = $this->requiredDate($attributes['service_date'], 'service_date')->toDateString();
        }

        if (array_key_exists('billed_amount', $attributes)) {
            $amount = $this->decimal($attributes['billed_amount'], 'billed_amount');
            $this->assertAmountDoesNotExceedInvoice($amount, $invoice->totalAmount);
            $updates['billed_amount'] = $amount;
        }

        if (array_key_exists('notes', $attributes)) {
            $updates['notes'] = $this->nullableString($attributes['notes']);
        }

        return $updates;
    }

    private function assertAmountDoesNotExceedInvoice(string $amount, string $invoiceTotal): void
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        /** @phpstan-ignore-next-line */
        if (bccomp($amount, $this->normalizedStoredDecimal($invoiceTotal), 2) === 1) {
            throw new UnprocessableEntityHttpException('Claim billed amount may not exceed the linked invoice total.');
        }
    }

    private function assertInvoiceEligible(InvoiceData $invoice): void
    {
        if (! in_array($invoice->status, [InvoiceStatus::ISSUED->value, InvoiceStatus::FINALIZED->value], true)) {
            throw new UnprocessableEntityHttpException('Claims may only be created from issued or finalized invoices.');
        }
    }

    private function assertPayerActive(PayerData $payer): void
    {
        if (! $payer->isActive) {
            throw new UnprocessableEntityHttpException('Inactive payers cannot be used for claim mutations.');
        }
    }

    private function decimal(mixed $value, string $field): string
    {
        $normalized = match (true) {
            is_int($value) => sprintf('%d.00', $value),
            is_float($value) => number_format($value, 2, '.', ''),
            is_string($value) => trim($value),
            default => null,
        };

        if ($normalized === null || ! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $normalized)) {
            throw new UnprocessableEntityHttpException('The '.$field.' field must be a positive decimal with up to two fraction digits.');
        }

        $decimal = number_format((float) $normalized, 2, '.', '');

        /** @psalm-suppress ArgumentTypeCoercion */
        if (bccomp($decimal, '0.00', 2) <= 0) {
            throw new UnprocessableEntityHttpException('The '.$field.' field must be a positive decimal with up to two fraction digits.');
        }

        return $decimal;
    }

    private function invoiceOrFail(string $tenantId, string $invoiceId): InvoiceData
    {
        $invoice = $this->invoiceRepository->findInTenant($tenantId, $invoiceId);

        if (! $invoice instanceof InvoiceData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $invoice;
    }

    private function nullableDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableUuid(mixed $value): ?string
    {
        $uuid = $this->nullableString($value);

        return $uuid === null ? null : $uuid;
    }

    private function payerOrFail(string $tenantId, string $payerId): PayerData
    {
        $payer = $this->payerRepository->findInTenant($tenantId, $payerId);

        if (! $payer instanceof PayerData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $payer;
    }

    private function requiredDate(mixed $value, string $field): CarbonImmutable
    {
        $date = $this->nullableDate($value);

        if (! $date instanceof CarbonImmutable) {
            throw new UnprocessableEntityHttpException('The '.$field.' field is required.');
        }

        return $date;
    }

    private function requiredString(mixed $value, string $field): string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            throw new UnprocessableEntityHttpException('The '.$field.' field is required.');
        }

        return $normalized;
    }

    private function normalizedStoredDecimal(string $value): string
    {
        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
            throw new LogicException('Stored invoice total must be a valid decimal string.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function resolvePolicy(
        string $tenantId,
        string $patientId,
        PayerData $payer,
        ?string $patientPolicyId,
    ): ?PatientInsurancePolicyData {
        if ($patientPolicyId === null) {
            return null;
        }

        $policy = $this->patientInsurancePolicyRepository->findInTenant($tenantId, $patientId, $patientPolicyId);

        if (! $policy instanceof PatientInsurancePolicyData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        if ($policy->insuranceCode !== $payer->insuranceCode) {
            throw new UnprocessableEntityHttpException('Claim payer insurance code must match the selected patient policy.');
        }

        return $policy;
    }

    /**
     * @return list<string>
     */
    private function serviceCategories(InvoiceData $invoice): array
    {
        $categories = [];

        foreach ($invoice->items as $item) {
            if ($item->serviceCategory === null) {
                continue;
            }

            $normalized = mb_strtolower(trim($item->serviceCategory));

            if ($normalized !== '') {
                $categories[$normalized] = true;
            }
        }

        return array_keys($categories);
    }
}
