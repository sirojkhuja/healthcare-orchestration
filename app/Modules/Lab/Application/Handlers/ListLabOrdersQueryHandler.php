<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Queries\ListLabOrdersQuery;
use App\Modules\Lab\Application\Services\LabOrderReadService;

final class ListLabOrdersQueryHandler
{
    public function __construct(
        private readonly LabOrderReadService $labOrderReadService,
    ) {}

    /**
     * @return list<LabOrderData>
     */
    public function handle(ListLabOrdersQuery $query): array
    {
        return $this->labOrderReadService->list($query->criteria);
    }
}
