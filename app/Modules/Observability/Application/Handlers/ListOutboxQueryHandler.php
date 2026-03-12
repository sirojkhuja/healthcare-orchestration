<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Queries\ListOutboxQuery;
use App\Modules\Observability\Application\Services\OutboxAdministrationService;
use App\Shared\Application\Data\OutboxMessage;

final class ListOutboxQueryHandler
{
    public function __construct(private readonly OutboxAdministrationService $outboxAdministrationService) {}

    /**
     * @return list<OutboxMessage>
     */
    public function handle(ListOutboxQuery $query): array
    {
        return $this->outboxAdministrationService->list($query->criteria);
    }
}
