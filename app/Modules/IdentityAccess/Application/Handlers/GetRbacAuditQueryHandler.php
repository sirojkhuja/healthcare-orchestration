<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\IdentityAccess\Application\Queries\GetRbacAuditQuery;
use App\Shared\Application\Contracts\TenantContext;

final class GetRbacAuditQueryHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditEventRepository $auditEventRepository,
    ) {}

    /**
     * @return list<AuditEventData>
     */
    public function handle(GetRbacAuditQuery $query): array
    {
        return $this->auditEventRepository->forActionPrefix(
            'rbac.',
            $this->tenantContext->requireTenantId(),
            $query->limit,
        );
    }
}
