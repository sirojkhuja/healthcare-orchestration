<?php

namespace App\Modules\Provider\Application\Contracts;

use App\Modules\Provider\Application\Data\ProviderData;
use Carbon\CarbonImmutable;

interface ProviderRepository
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     middle_name: ?string,
     *     preferred_name: ?string,
     *     provider_type: string,
     *     email: ?string,
     *     phone: ?string,
     *     clinic_id: ?string,
     *     notes: ?string
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): ProviderData;

    public function findInTenant(string $tenantId, string $providerId, bool $withDeleted = false): ?ProviderData;

    /**
     * @return list<ProviderData>
     */
    public function listForTenant(string $tenantId): array;

    public function softDelete(string $tenantId, string $providerId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, string|null>  $updates
     */
    public function update(string $tenantId, string $providerId, array $updates): ?ProviderData;
}
