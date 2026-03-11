<?php

namespace App\Modules\Insurance\Application\Contracts;

use App\Modules\Insurance\Application\Data\PayerData;

interface PayerRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): PayerData;

    public function delete(string $tenantId, string $payerId): bool;

    public function existsCode(string $tenantId, string $code, ?string $ignorePayerId = null): bool;

    public function existsInsuranceCode(string $tenantId, string $insuranceCode, ?string $ignorePayerId = null): bool;

    public function findInTenant(string $tenantId, string $payerId): ?PayerData;

    public function isReferenced(string $tenantId, string $payerId): bool;

    /**
     * @return list<PayerData>
     */
    public function listForTenant(
        string $tenantId,
        ?string $query = null,
        ?string $insuranceCode = null,
        ?bool $isActive = null,
        int $limit = 25,
    ): array;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $payerId, array $updates): ?PayerData;
}
