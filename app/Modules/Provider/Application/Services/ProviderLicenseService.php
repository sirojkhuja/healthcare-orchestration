<?php

namespace App\Modules\Provider\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Provider\Application\Contracts\ProviderLicenseRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Data\ProviderLicenseData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ProviderLicenseService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ProviderRepository $providerRepository,
        private readonly ProviderLicenseRepository $providerLicenseRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function add(string $providerId, array $attributes): ProviderLicenseData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $provider = $this->providerOrFail($providerId);
        $normalized = $this->normalizeCreate($attributes);

        if ($this->providerLicenseRepository->existsDuplicate(
            $tenantId,
            $providerId,
            $normalized['license_type'],
            $normalized['license_number'],
        )) {
            throw new ConflictHttpException('The provider license already exists.');
        }

        $license = $this->providerLicenseRepository->create($tenantId, $providerId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.license_added',
            objectType: 'provider',
            objectId: $provider->providerId,
            after: ['license' => $license->toArray()],
        ));

        return $license;
    }

    /**
     * @return list<ProviderLicenseData>
     */
    public function list(string $providerId): array
    {
        $this->providerOrFail($providerId);

        return $this->providerLicenseRepository->listForProvider(
            $this->tenantContext->requireTenantId(),
            $providerId,
        );
    }

    public function remove(string $providerId, string $licenseId): ProviderLicenseData
    {
        $provider = $this->providerOrFail($providerId);
        $license = $this->licenseOrFail($providerId, $licenseId);

        if (! $this->providerLicenseRepository->delete(
            $this->tenantContext->requireTenantId(),
            $providerId,
            $licenseId,
        )) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'providers.license_removed',
            objectType: 'provider',
            objectId: $provider->providerId,
            before: ['license' => $license->toArray()],
        ));

        return $license;
    }

    private function licenseOrFail(string $providerId, string $licenseId): ProviderLicenseData
    {
        $license = $this->providerLicenseRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $providerId,
            $licenseId,
        );

        if (! $license instanceof ProviderLicenseData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $license;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *      license_type: string,
     *      license_number: string,
     *      issuing_authority: string,
     *      jurisdiction: ?string,
     *      issued_on: ?CarbonImmutable,
     *      expires_on: ?CarbonImmutable,
     *      notes: ?string
     *  }
     */
    private function normalizeCreate(array $attributes): array
    {
        $issuedOn = $this->nullableDate($attributes['issued_on'] ?? null);
        $expiresOn = $this->nullableDate($attributes['expires_on'] ?? null);

        if ($issuedOn instanceof CarbonImmutable && $expiresOn instanceof CarbonImmutable && $expiresOn->lt($issuedOn)) {
            throw new UnprocessableEntityHttpException('The provider license expiry date must not be earlier than the issue date.');
        }

        return [
            'license_type' => $this->snakeIdentifier($attributes['license_type'] ?? null, 'license_type'),
            'license_number' => $this->requiredString($attributes['license_number'] ?? null, 'license_number'),
            'issuing_authority' => $this->requiredString($attributes['issuing_authority'] ?? null, 'issuing_authority'),
            'jurisdiction' => $this->nullableString($attributes['jurisdiction'] ?? null),
            'issued_on' => $issuedOn,
            'expires_on' => $expiresOn,
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
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

    private function snakeIdentifier(mixed $value, string $field): string
    {
        $string = $this->requiredString($value, $field);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($string));
        $result = trim(is_string($normalized) ? $normalized : '', '_');

        if ($result === '') {
            throw new UnprocessableEntityHttpException('The '.$field.' field is not valid.');
        }

        return $result;
    }
}
