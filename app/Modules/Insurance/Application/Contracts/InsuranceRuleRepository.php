<?php

namespace App\Modules\Insurance\Application\Contracts;

use App\Modules\Insurance\Application\Data\InsuranceRuleData;

interface InsuranceRuleRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): InsuranceRuleData;

    public function delete(string $tenantId, string $ruleId): bool;

    public function existsCode(string $tenantId, string $code, ?string $ignoreRuleId = null): bool;

    public function findInTenant(string $tenantId, string $ruleId): ?InsuranceRuleData;

    /**
     * @return list<InsuranceRuleData>
     */
    public function listActiveForPayer(string $tenantId, string $payerId): array;

    /**
     * @return list<InsuranceRuleData>
     */
    public function listForTenant(
        string $tenantId,
        ?string $query = null,
        ?string $payerId = null,
        ?string $serviceCategory = null,
        ?bool $isActive = null,
        int $limit = 25,
    ): array;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $ruleId, array $updates): ?InsuranceRuleData;
}
