<?php

namespace App\Modules\Scheduling\Infrastructure\Cache;

use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Shared\Application\Contracts\TenantCache;

final class TenantScopedAvailabilityCacheInvalidator implements AvailabilityCacheInvalidator
{
    public function __construct(
        private readonly TenantCache $tenantCache,
    ) {}

    #[\Override]
    public function invalidate(string $tenantId): void
    {
        $this->tenantCache->invalidate('availability', $tenantId);
    }
}
