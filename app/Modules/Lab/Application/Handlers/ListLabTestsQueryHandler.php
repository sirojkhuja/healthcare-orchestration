<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Data\LabTestData;
use App\Modules\Lab\Application\Queries\ListLabTestsQuery;
use App\Modules\Lab\Application\Services\LabTestCatalogService;

final class ListLabTestsQueryHandler
{
    public function __construct(
        private readonly LabTestCatalogService $labTestCatalogService,
    ) {}

    /**
     * @return list<LabTestData>
     */
    public function handle(ListLabTestsQuery $query): array
    {
        return $this->labTestCatalogService->list($query->criteria);
    }
}
