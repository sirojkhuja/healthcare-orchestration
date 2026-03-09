<?php

namespace App\Modules\Provider\Application\Contracts;

use App\Modules\Provider\Application\Data\ProviderLicenseData;

interface ProviderLicenseRepository
{
    /**
     * @param  array{
     *      license_type: string,
     *      license_number: string,
     *      issuing_authority: string,
     *      jurisdiction: ?string,
     *      issued_on: ?\Carbon\CarbonImmutable,
     *      expires_on: ?\Carbon\CarbonImmutable,
     *      notes: ?string
     *  }  $attributes
     */
    public function create(string $tenantId, string $providerId, array $attributes): ProviderLicenseData;

    public function delete(string $tenantId, string $providerId, string $licenseId): bool;

    public function existsDuplicate(
        string $tenantId,
        string $providerId,
        string $licenseType,
        string $licenseNumber,
    ): bool;

    public function findInTenant(string $tenantId, string $providerId, string $licenseId): ?ProviderLicenseData;

    /**
     * @return list<ProviderLicenseData>
     */
    public function listForProvider(string $tenantId, string $providerId): array;
}
