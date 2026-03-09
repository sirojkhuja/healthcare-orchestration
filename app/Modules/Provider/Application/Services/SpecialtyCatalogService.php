<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Contracts\SpecialtyRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Data\ProviderSpecialtyData;
use App\Modules\Provider\Application\Data\SpecialtyData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SpecialtyCatalogService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
        private readonly SpecialtyRepository $specialtyRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SpecialtyData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $name = $this->requiredString($attributes['name'] ?? null, 'name');
        $description = $this->nullableString($attributes['description'] ?? null);

        if ($this->specialtyRepository->nameExists($tenantId, $name)) {
            throw new ConflictHttpException('A specialty with this name already exists.');
        }

        $specialty = $this->specialtyRepository->create($tenantId, $name, $description);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'provider_specialties.created',
            objectType: 'specialty',
            objectId: $specialty->specialtyId,
            after: $specialty->toArray(),
        ));

        return $specialty;
    }

    public function delete(string $specialtyId): SpecialtyData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $specialty = $this->specialtyOrFail($specialtyId);

        if ($this->specialtyRepository->hasAssignments($tenantId, $specialtyId)) {
            throw new ConflictHttpException('The specialty is assigned to one or more providers.');
        }

        if (! $this->specialtyRepository->delete($tenantId, $specialtyId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'provider_specialties.deleted',
            objectType: 'specialty',
            objectId: $specialtyId,
            before: $specialty->toArray(),
        ));

        return $specialty;
    }

    /**
     * @return list<SpecialtyData>
     */
    public function listCatalog(): array
    {
        return $this->specialtyRepository->listForTenant($this->tenantContext->requireTenantId());
    }

    /**
     * @return list<ProviderSpecialtyData>
     */
    public function listProviderSpecialties(string $providerId): array
    {
        $this->providerOrFail($providerId);

        return $this->specialtyRepository->listForProvider($this->tenantContext->requireTenantId(), $providerId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<ProviderSpecialtyData>
     */
    public function setProviderSpecialties(string $providerId, array $attributes): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($providerId);
        $assignments = $this->normalizeAssignments($attributes['specialties'] ?? null);
        $before = $this->specialtyRepository->listForProvider($tenantId, $providerId);

        /** @var list<ProviderSpecialtyData> $after */
        $after = DB::transaction(fn (): array => $this->specialtyRepository->replaceProviderAssignments(
            $tenantId,
            $providerId,
            $assignments,
        ));

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.specialties_set',
            objectType: 'provider',
            objectId: $provider->providerId,
            before: ['specialties' => $this->specialtiesToArray($before)],
            after: ['specialties' => $this->specialtiesToArray($after)],
        ));

        return $after;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $specialtyId, array $attributes): SpecialtyData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $specialty = $this->specialtyOrFail($specialtyId);
        $name = array_key_exists('name', $attributes)
            ? $this->requiredString($attributes['name'], 'name')
            : $specialty->name;
        $description = array_key_exists('description', $attributes)
            ? $this->nullableString($attributes['description'])
            : $specialty->description;

        if ($this->specialtyRepository->nameExists($tenantId, $name, $specialtyId)) {
            throw new ConflictHttpException('A specialty with this name already exists.');
        }

        $updated = $this->specialtyRepository->update($tenantId, $specialtyId, $name, $description);

        if (! $updated instanceof SpecialtyData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'provider_specialties.updated',
            objectType: 'specialty',
            objectId: $specialtyId,
            before: $specialty->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    /**
     * @param  list<ProviderSpecialtyData>  $specialties
     * @return list<array<string, mixed>>
     */
    private function specialtiesToArray(array $specialties): array
    {
        return array_map(
            static fn (ProviderSpecialtyData $specialty): array => $specialty->toArray(),
            $specialties,
        );
    }

    /**
     * @return list<array{specialty_id: string, is_primary: bool}>
     */
    private function normalizeAssignments(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $assignments = [];
        $seen = [];
        $primaryCount = 0;

        foreach ($value as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }

            $specialtyId = $this->requiredString($assignment['specialty_id'] ?? null, 'specialty_id');
            $key = mb_strtolower($specialtyId);

            if (isset($seen[$key])) {
                throw new UnprocessableEntityHttpException('Each specialty may appear only once in the replacement payload.');
            }

            $specialty = $this->specialtyRepository->findInTenant($tenantId, $specialtyId);

            if (! $specialty instanceof SpecialtyData) {
                throw new UnprocessableEntityHttpException('One or more specialties do not exist in the current tenant scope.');
            }

            $isPrimary = (bool) ($assignment['is_primary'] ?? false);
            $primaryCount += $isPrimary ? 1 : 0;
            $seen[$key] = true;
            $assignments[] = [
                'specialty_id' => $specialtyId,
                'is_primary' => $isPrimary,
            ];
        }

        if ($primaryCount > 1) {
            throw new UnprocessableEntityHttpException('At most one provider specialty may be primary.');
        }

        return $assignments;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function providerOrFail(string $providerId): ProviderData
    {
        $provider = $this->providerRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $providerId,
        );

        if (! $provider instanceof ProviderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
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

    private function specialtyOrFail(string $specialtyId): SpecialtyData
    {
        $specialty = $this->specialtyRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $specialtyId,
        );

        if (! $specialty instanceof SpecialtyData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $specialty;
    }
}
