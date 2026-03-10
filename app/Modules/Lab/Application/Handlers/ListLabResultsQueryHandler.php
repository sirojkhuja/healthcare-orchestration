<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Data\LabResultData;
use App\Modules\Lab\Application\Queries\ListLabResultsQuery;
use App\Modules\Lab\Application\Services\LabOrderReadService;

final class ListLabResultsQueryHandler
{
    public function __construct(
        private readonly LabOrderReadService $labOrderReadService,
    ) {}

    /**
     * @return list<LabResultData>
     */
    public function handle(ListLabResultsQuery $query): array
    {
        return $this->labOrderReadService->listResults($query->orderId);
    }
}
