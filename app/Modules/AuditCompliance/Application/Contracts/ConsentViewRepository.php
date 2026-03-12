<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\ConsentViewData;
use App\Modules\AuditCompliance\Application\Data\ConsentViewSearchCriteria;

interface ConsentViewRepository
{
    public function findInTenant(string $tenantId, string $consentId): ?ConsentViewData;

    /**
     * @return list<ConsentViewData>
     */
    public function listForTenant(string $tenantId, ConsentViewSearchCriteria $criteria): array;
}
