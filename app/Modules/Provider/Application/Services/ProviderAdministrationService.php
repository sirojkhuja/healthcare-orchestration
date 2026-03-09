<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderProfileRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProviderAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
        private readonly ProviderProfileRepository $providerProfileRepository,
        private readonly ProviderAttributeNormalizer $providerAttributeNormalizer,
        private readonly AvailabilityCacheInvalidator $availabilityCacheInvalidator,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ProviderData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->providerAttributeNormalizer->normalizeCreate($attributes, $tenantId);
        $provider = $this->providerRepository->create($tenantId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.created',
            objectType: 'provider',
            objectId: $provider->providerId,
            after: $provider->toArray(),
        ));

        return $provider;
    }

    public function delete(string $providerId): ProviderData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($providerId);
        $deletedAt = CarbonImmutable::now();

        if (! $this->providerRepository->softDelete($tenantId, $provider->providerId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->providerRepository->findInTenant($tenantId, $provider->providerId, true);

        if (! $deleted instanceof ProviderData) {
            throw new LogicException('Deleted provider could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.deleted',
            objectType: 'provider',
            objectId: $provider->providerId,
            before: $provider->toArray(),
            after: $deleted->toArray(),
        ));
        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return $deleted;
    }

    public function get(string $providerId): ProviderData
    {
        return $this->providerOrFail($providerId);
    }

    /**
     * @return list<ProviderData>
     */
    public function list(): array
    {
        return $this->providerRepository->listForTenant($this->tenantContext->requireTenantId());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $providerId, array $attributes): ProviderData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($providerId);
        $updates = $this->providerAttributeNormalizer->normalizePatch($provider, $attributes);

        if ($updates === []) {
            return $provider;
        }

        $updated = $this->providerRepository->update($tenantId, $provider->providerId, $updates);

        if (! $updated instanceof ProviderData) {
            throw new LogicException('Updated provider could not be reloaded.');
        }

        if (
            array_key_exists('clinic_id', $updates)
            && $updates['clinic_id'] !== $provider->clinicId
        ) {
            $this->providerProfileRepository->clearLocationFields($tenantId, $provider->providerId);
            $updated = $this->providerRepository->findInTenant($tenantId, $provider->providerId)
                ?? throw new LogicException('Updated provider could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.updated',
            objectType: 'provider',
            objectId: $provider->providerId,
            before: $provider->toArray(),
            after: $updated->toArray(),
        ));

        if (
            array_key_exists('clinic_id', $updates)
            && $updates['clinic_id'] !== $provider->clinicId
        ) {
            $this->availabilityCacheInvalidator->invalidate($tenantId);
        }

        return $updated;
    }

    private function providerOrFail(string $providerId): ProviderData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerRepository->findInTenant($tenantId, $providerId);

        if (! $provider instanceof ProviderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $provider;
    }
}
