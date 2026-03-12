<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Queries\ListDataAccessRequestsQuery;
use App\Modules\AuditCompliance\Application\Services\DataAccessRequestService;

final class ListDataAccessRequestsQueryHandler
{
    public function __construct(
        private readonly DataAccessRequestService $dataAccessRequestService,
    ) {}

    /**
     * @return list<DataAccessRequestData>
     */
    public function handle(ListDataAccessRequestsQuery $query): array
    {
        return $this->dataAccessRequestService->list($query->criteria);
    }
}
