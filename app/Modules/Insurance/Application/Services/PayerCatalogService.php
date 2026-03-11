<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Insurance\Application\Contracts\PayerRepository;
use App\Modules\Insurance\Application\Data\PayerData;
use App\Shared\Application\Contracts\TenantContext;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PayerCatalogService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PayerRepository $payerRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PayerData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->normalizeCreate($attributes);

        if ($this->payerRepository->existsCode($tenantId, $normalized['code'])) {
            throw new ConflictHttpException('The payer code is already used in the current tenant.');
        }

        if ($this->payerRepository->existsInsuranceCode($tenantId, $normalized['insurance_code'])) {
            throw new ConflictHttpException('The insurance code is already used in the current tenant.');
        }

        $payer = $this->payerRepository->create($tenantId, $normalized);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payers.created',
            objectType: 'payer',
            objectId: $payer->payerId,
            after: $payer->toArray(),
        ));

        return $payer;
    }

    public function delete(string $payerId): PayerData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $payer = $this->payerOrFail($tenantId, $payerId);

        if ($this->payerRepository->isReferenced($tenantId, $payerId)) {
            throw new ConflictHttpException('Payers referenced by active claims cannot be deleted.');
        }

        if (! $this->payerRepository->delete($tenantId, $payerId)) {
            throw new LogicException('Payer deletion did not remove the stored record.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payers.deleted',
            objectType: 'payer',
            objectId: $payer->payerId,
            before: $payer->toArray(),
        ));

        return $payer;
    }

    /**
     * @return list<PayerData>
     */
    public function list(?string $query, ?string $insuranceCode, ?bool $isActive, int $limit): array
    {
        return $this->payerRepository->listForTenant(
            tenantId: $this->tenantContext->requireTenantId(),
            query: $this->nullableString($query),
            insuranceCode: $insuranceCode === null ? null : mb_strtolower(trim($insuranceCode)),
            isActive: $isActive,
            limit: $limit,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $payerId, array $attributes): PayerData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $payer = $this->payerOrFail($tenantId, $payerId);
        $updates = $this->normalizePatch($attributes);

        if ($updates === []) {
            return $payer;
        }

        if (array_key_exists('code', $updates) && $this->payerRepository->existsCode($tenantId, $updates['code'], $payerId)) {
            throw new ConflictHttpException('The payer code is already used in the current tenant.');
        }

        if (
            array_key_exists('insurance_code', $updates)
            && $this->payerRepository->existsInsuranceCode($tenantId, $updates['insurance_code'], $payerId)
        ) {
            throw new ConflictHttpException('The insurance code is already used in the current tenant.');
        }

        $updated = $this->payerRepository->update($tenantId, $payerId, $updates);

        if (! $updated instanceof PayerData) {
            throw new LogicException('Updated payer could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payers.updated',
            objectType: 'payer',
            objectId: $updated->payerId,
            before: $payer->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     code: string,
     *     name: string,
     *     insurance_code: string,
     *     contact_name: string|null,
     *     contact_email: string|null,
     *     contact_phone: string|null,
     *     is_active: bool,
     *     notes: string|null
     * }
     */
    private function normalizeCreate(array $attributes): array
    {
        return [
            'code' => mb_strtoupper($this->requiredString($attributes['code'] ?? null, 'code')),
            'name' => $this->requiredString($attributes['name'] ?? null, 'name'),
            'insurance_code' => mb_strtolower($this->requiredString($attributes['insurance_code'] ?? null, 'insurance_code')),
            'contact_name' => $this->nullableString($attributes['contact_name'] ?? null),
            'contact_email' => $this->nullableEmail($attributes['contact_email'] ?? null),
            'contact_phone' => $this->nullableString($attributes['contact_phone'] ?? null),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     code?: string,
     *     name?: string,
     *     insurance_code?: string,
     *     contact_name?: string|null,
     *     contact_email?: string|null,
     *     contact_phone?: string|null,
     *     notes?: string|null,
     *     is_active?: bool
     * }
     */
    private function normalizePatch(array $attributes): array
    {
        $updates = [];

        if (array_key_exists('code', $attributes)) {
            $updates['code'] = mb_strtoupper($this->requiredString($attributes['code'], 'code'));
        }

        if (array_key_exists('name', $attributes)) {
            $updates['name'] = $this->requiredString($attributes['name'], 'name');
        }

        if (array_key_exists('insurance_code', $attributes)) {
            $updates['insurance_code'] = mb_strtolower($this->requiredString($attributes['insurance_code'], 'insurance_code'));
        }

        if (array_key_exists('contact_name', $attributes)) {
            $updates['contact_name'] = $this->nullableString($attributes['contact_name']);
        }

        if (array_key_exists('contact_email', $attributes)) {
            $updates['contact_email'] = $this->nullableEmail($attributes['contact_email']);
        }

        if (array_key_exists('contact_phone', $attributes)) {
            $updates['contact_phone'] = $this->nullableString($attributes['contact_phone']);
        }

        if (array_key_exists('notes', $attributes)) {
            $updates['notes'] = $this->nullableString($attributes['notes']);
        }

        if (array_key_exists('is_active', $attributes)) {
            $updates['is_active'] = (bool) $attributes['is_active'];
        }

        return $updates;
    }

    private function nullableEmail(mixed $value): ?string
    {
        $email = $this->nullableString($value);

        return $email === null ? null : mb_strtolower($email);
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
}
