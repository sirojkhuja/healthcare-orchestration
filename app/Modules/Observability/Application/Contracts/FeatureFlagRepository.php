<?php

namespace App\Modules\Observability\Application\Contracts;

interface FeatureFlagRepository
{
    /**
     * @return array<string, array{enabled: bool, updated_at: \Carbon\CarbonImmutable}>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @param  array<string, bool>  $flags
     */
    public function saveMany(string $tenantId, array $flags): void;
}
