<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Queries\GetPriceListQuery;
use App\Modules\Billing\Application\Services\PriceListCatalogService;

final readonly class GetPriceListQueryHandler
{
    public function __construct(private PriceListCatalogService $service) {}

    public function handle(GetPriceListQuery $query): PriceListData
    {
        return $this->service->get($query->priceListId);
    }
}
