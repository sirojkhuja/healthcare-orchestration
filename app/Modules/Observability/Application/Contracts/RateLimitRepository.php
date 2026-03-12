<?php

namespace App\Modules\Observability\Application\Contracts;

interface RateLimitRepository
{
    /**
     * @return array<string, array{requests_per_minute: int, burst: int, updated_at: \Carbon\CarbonImmutable}>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @param  array<string, array{requests_per_minute: int, burst: int}>  $limits
     */
    public function saveMany(string $tenantId, array $limits): void;
}
