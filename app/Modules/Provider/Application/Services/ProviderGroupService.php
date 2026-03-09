<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderGroupRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Data\ProviderGroupData;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ProviderGroupService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
        private readonly ProviderGroupRepository $providerGroupRepository,
        private readonly ClinicRepository $clinicRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ProviderGroupData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $name = $this->requiredString($attributes['name'] ?? null, 'name');
        $description = $this->nullableString($attributes['description'] ?? null);
        $clinicId = $this->nullableUuid($attributes['clinic_id'] ?? null);

        if ($clinicId !== null && ! $this->clinicRepository->findClinic($tenantId, $clinicId)) {
            throw new UnprocessableEntityHttpException('The selected clinic does not exist in the current tenant scope.');
        }

        if ($this->providerGroupRepository->nameExists($tenantId, $name)) {
            throw new ConflictHttpException('A provider group with this name already exists.');
        }

        $group = $this->providerGroupRepository->create($tenantId, $name, $description, $clinicId);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'provider_groups.created',
            objectType: 'provider_group',
            objectId: $group->groupId,
            after: $group->toArray(),
        ));

        return $group;
    }

    /**
     * @return list<ProviderGroupData>
     */
    public function list(): array
    {
        return $this->providerGroupRepository->listForTenant($this->tenantContext->requireTenantId());
    }

    /**
     * @param  list<mixed>  $providerIds
     */
    public function setMembers(string $groupId, array $providerIds): ProviderGroupData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $group = $this->groupOrFail($groupId);
        $normalizedProviderIds = $this->normalizeProviderIds($providerIds);

        foreach ($normalizedProviderIds as $providerId) {
            $this->providerOrFail($providerId);
        }

        $before = $group;
        /** @var ProviderGroupData $after */
        $after = DB::transaction(fn (): ProviderGroupData => $this->providerGroupRepository->replaceMembers(
            $tenantId,
            $groupId,
            $normalizedProviderIds,
        ));

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'provider_groups.members_updated',
            objectType: 'provider_group',
            objectId: $groupId,
            before: $before->toArray(),
            after: $after->toArray(),
        ));

        return $after;
    }

    private function groupOrFail(string $groupId): ProviderGroupData
    {
        $group = $this->providerGroupRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $groupId,
        );

        if (! $group instanceof ProviderGroupData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $group;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function nullableUuid(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  list<mixed>  $providerIds
     * @return list<string>
     */
    private function normalizeProviderIds(array $providerIds): array
    {
        $seen = [];
        $normalized = [];

        foreach ($providerIds as $providerId) {
            if (! is_string($providerId) || $providerId === '') {
                continue;
            }

            $key = mb_strtolower($providerId);

            if (isset($seen[$key])) {
                throw new UnprocessableEntityHttpException('Provider group membership payload contains duplicate provider identifiers.');
            }

            $seen[$key] = true;
            $normalized[] = $providerId;
        }

        return $normalized;
    }

    private function providerOrFail(string $providerId): ProviderData
    {
        $provider = $this->providerRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $providerId,
        );

        if (! $provider instanceof ProviderData) {
            throw new UnprocessableEntityHttpException('One or more providers do not exist in the current tenant scope.');
        }

        return $provider;
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
