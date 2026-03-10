<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\BillableServiceData;
use App\Modules\Billing\Application\Queries\ListBillableServicesQuery;
use App\Modules\Billing\Application\Services\BillableServiceCatalogService;

final readonly class ListBillableServicesQueryHandler
{
    public function __construct(private BillableServiceCatalogService $service) {}

    /**
     * @return list<BillableServiceData>
     */
    public function handle(ListBillableServicesQuery $query): array
    {
        return $this->service->list($query->criteria);
    }
}
