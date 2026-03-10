<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Queries\SearchLabOrdersQuery;
use App\Modules\Lab\Application\Services\LabOrderReadService;

final class SearchLabOrdersQueryHandler
{
    public function __construct(
        private readonly LabOrderReadService $labOrderReadService,
    ) {}

    /**
     * @return list<LabOrderData>
     */
    public function handle(SearchLabOrdersQuery $query): array
    {
        return $this->labOrderReadService->search($query->criteria);
    }
}
