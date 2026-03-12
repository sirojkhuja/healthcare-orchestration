<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Queries\GetDataAccessRequestQuery;
use App\Modules\AuditCompliance\Application\Services\DataAccessRequestService;

final class GetDataAccessRequestQueryHandler
{
    public function __construct(
        private readonly DataAccessRequestService $dataAccessRequestService,
    ) {}

    public function handle(GetDataAccessRequestQuery $query): DataAccessRequestData
    {
        return $this->dataAccessRequestService->get($query->requestId);
    }
}
