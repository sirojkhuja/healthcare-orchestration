<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Insurance\Application\Contracts\InsuranceRuleRepository;
use App\Modules\Insurance\Application\Contracts\PayerRepository;
use App\Modules\Insurance\Application\Data\InsuranceRuleData;
use App\Modules\Insurance\Application\Data\PayerData;
use App\Shared\Application\Contracts\TenantContext;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InsuranceRuleService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InsuranceRuleRepository $insuranceRuleRepository,
        private readonly PayerRepository $payerRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): InsuranceRuleData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->normalizeCreate($attributes);
        $this->payerOrFail($tenantId, $normalized['payer_id']);

        if ($this->insuranceRuleRepository->existsCode($tenantId, $normalized['code'])) {
            throw new ConflictHttpException('The insurance rule code is already used in the current tenant.');
        }

        $rule = $this->insuranceRuleRepository->create($tenantId, $normalized);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'insurance_rules.created',
            objectType: 'insurance_rule',
            objectId: $rule->ruleId,
            after: $rule->toArray(),
        ));

        return $rule;
    }

    public function delete(string $ruleId): InsuranceRuleData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $rule = $this->ruleOrFail($tenantId, $ruleId);

        if (! $this->insuranceRuleRepository->delete($tenantId, $ruleId)) {
            throw new LogicException('Insurance rule deletion did not remove the stored record.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'insurance_rules.deleted',
            objectType: 'insurance_rule',
            objectId: $rule->ruleId,
            before: $rule->toArray(),
        ));

        return $rule;
    }

    /**
     * @return list<InsuranceRuleData>
     */
    public function list(?string $query, ?string $payerId, ?string $serviceCategory, ?bool $isActive, int $limit): array
    {
        return $this->insuranceRuleRepository->listForTenant(
            tenantId: $this->tenantContext->requireTenantId(),
            query: $this->nullableString($query),
            payerId: $payerId,
            serviceCategory: $serviceCategory === null ? null : mb_strtolower(trim($serviceCategory)),
            isActive: $isActive,
            limit: $limit,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $ruleId, array $attributes): InsuranceRuleData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $rule = $this->ruleOrFail($tenantId, $ruleId);
        $updates = $this->normalizePatch($attributes);

        if ($updates === []) {
            return $rule;
        }

        if (array_key_exists('payer_id', $updates)) {
            $this->payerOrFail($tenantId, $updates['payer_id']);
        }

        if (array_key_exists('code', $updates) && $this->insuranceRuleRepository->existsCode($tenantId, $updates['code'], $ruleId)) {
            throw new ConflictHttpException('The insurance rule code is already used in the current tenant.');
        }

        $updated = $this->insuranceRuleRepository->update($tenantId, $ruleId, $updates);

        if (! $updated instanceof InsuranceRuleData) {
            throw new LogicException('Updated insurance rule could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'insurance_rules.updated',
            objectType: 'insurance_rule',
            objectId: $updated->ruleId,
            before: $rule->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     payer_id: string,
     *     code: string,
     *     name: string,
     *     service_category: string|null,
     *     requires_primary_policy: bool,
     *     requires_attachment: bool,
     *     max_claim_amount: string|null,
     *     submission_window_days: int|null,
     *     is_active: bool,
     *     notes: string|null
     * }
     */
    private function normalizeCreate(array $attributes): array
    {
        return [
            'payer_id' => $this->requiredString($attributes['payer_id'] ?? null, 'payer_id'),
            'code' => mb_strtoupper($this->requiredString($attributes['code'] ?? null, 'code')),
            'name' => $this->requiredString($attributes['name'] ?? null, 'name'),
            'service_category' => $this->nullableLowerString($attributes['service_category'] ?? null),
            'requires_primary_policy' => (bool) ($attributes['requires_primary_policy'] ?? false),
            'requires_attachment' => (bool) ($attributes['requires_attachment'] ?? false),
            'max_claim_amount' => $this->nullableDecimal($attributes['max_claim_amount'] ?? null, 'max_claim_amount'),
            'submission_window_days' => $this->nullablePositiveInteger($attributes['submission_window_days'] ?? null, 'submission_window_days'),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     payer_id?: string,
     *     code?: string,
     *     name?: string,
     *     service_category?: string|null,
     *     requires_primary_policy?: bool,
     *     requires_attachment?: bool,
     *     max_claim_amount?: string|null,
     *     submission_window_days?: int|null,
     *     is_active?: bool,
     *     notes?: string|null
     * }
     */
    private function normalizePatch(array $attributes): array
    {
        $updates = [];

        if (array_key_exists('payer_id', $attributes)) {
            $updates['payer_id'] = $this->requiredString($attributes['payer_id'], 'payer_id');
        }

        if (array_key_exists('code', $attributes)) {
            $updates['code'] = mb_strtoupper($this->requiredString($attributes['code'], 'code'));
        }

        if (array_key_exists('name', $attributes)) {
            $updates['name'] = $this->requiredString($attributes['name'], 'name');
        }

        if (array_key_exists('service_category', $attributes)) {
            $updates['service_category'] = $this->nullableLowerString($attributes['service_category']);
        }

        if (array_key_exists('requires_primary_policy', $attributes)) {
            $updates['requires_primary_policy'] = (bool) $attributes['requires_primary_policy'];
        }

        if (array_key_exists('requires_attachment', $attributes)) {
            $updates['requires_attachment'] = (bool) $attributes['requires_attachment'];
        }

        if (array_key_exists('max_claim_amount', $attributes)) {
            $updates['max_claim_amount'] = $this->nullableDecimal($attributes['max_claim_amount'], 'max_claim_amount');
        }

        if (array_key_exists('submission_window_days', $attributes)) {
            $updates['submission_window_days'] = $this->nullablePositiveInteger($attributes['submission_window_days'], 'submission_window_days');
        }

        if (array_key_exists('is_active', $attributes)) {
            $updates['is_active'] = (bool) $attributes['is_active'];
        }

        if (array_key_exists('notes', $attributes)) {
            $updates['notes'] = $this->nullableString($attributes['notes']);
        }

        return $updates;
    }

    private function nullableDecimal(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

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

    private function nullableLowerString(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);

        return $normalized === null ? null : mb_strtolower($normalized);
    }

    private function nullablePositiveInteger(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value <= 0) {
            throw new UnprocessableEntityHttpException('The '.$field.' field must be a positive integer.');
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function payerOrFail(string $tenantId, string $payerId): PayerData
    {
        $payer = $this->payerRepository->findInTenant($tenantId, $payerId);

        if (! $payer instanceof PayerData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $payer;
    }

    private function requiredString(mixed $value, string $field): string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            throw new UnprocessableEntityHttpException('The '.$field.' field is required.');
        }

        return $normalized;
    }

    private function ruleOrFail(string $tenantId, string $ruleId): InsuranceRuleData
    {
        $rule = $this->insuranceRuleRepository->findInTenant($tenantId, $ruleId);

        if (! $rule instanceof InsuranceRuleData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $rule;
    }
}
