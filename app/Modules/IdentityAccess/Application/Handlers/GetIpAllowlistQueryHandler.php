<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\TenantIpAllowlistRepository;
use App\Modules\IdentityAccess\Application\Data\TenantIpAllowlistEntryData;
use App\Modules\IdentityAccess\Application\Queries\GetIpAllowlistQuery;
use App\Shared\Application\Contracts\TenantContext;

final class GetIpAllowlistQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantIpAllowlistRepository $tenantIpAllowlistRepository,
    ) {}

    /**
     * @return list<TenantIpAllowlistEntryData>
     */
    public function handle(GetIpAllowlistQuery $query): array
    {
        return $this->tenantIpAllowlistRepository->listForTenant($this->tenantContext->requireTenantId());
    }
}
