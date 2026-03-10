<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Queries\ListPriceListsQuery;
use App\Modules\Billing\Application\Services\PriceListCatalogService;

final readonly class ListPriceListsQueryHandler
{
    public function __construct(private PriceListCatalogService $service) {}

    /**
     * @return list<PriceListData>
     */
    public function handle(ListPriceListsQuery $query): array
    {
        return $this->service->list($query->criteria);
    }
}
