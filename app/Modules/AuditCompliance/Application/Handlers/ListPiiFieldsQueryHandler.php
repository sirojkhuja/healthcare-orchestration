<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\PiiFieldData;
use App\Modules\AuditCompliance\Application\Queries\ListPiiFieldsQuery;
use App\Modules\AuditCompliance\Application\Services\PiiGovernanceService;

final class ListPiiFieldsQueryHandler
{
    public function __construct(
        private readonly PiiGovernanceService $piiGovernanceService,
    ) {}

    /**
     * @return list<PiiFieldData>
     */
    public function handle(ListPiiFieldsQuery $query): array
    {
        return $this->piiGovernanceService->listFields();
    }
}
