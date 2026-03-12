<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\ConsentViewData;
use App\Modules\AuditCompliance\Application\Queries\ListConsentsQuery;
use App\Modules\AuditCompliance\Application\Services\ConsentViewService;

final class ListConsentsQueryHandler
{
    public function __construct(
        private readonly ConsentViewService $consentViewService,
    ) {}

    /**
     * @return list<ConsentViewData>
     */
    public function handle(ListConsentsQuery $query): array
    {
        return $this->consentViewService->list($query->criteria);
    }
}
