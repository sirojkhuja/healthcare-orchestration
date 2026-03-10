<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\SetPriceListItemsCommand;
use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Services\PriceListCatalogService;

final readonly class SetPriceListItemsCommandHandler
{
    public function __construct(private PriceListCatalogService $service) {}

    public function handle(SetPriceListItemsCommand $command): PriceListData
    {
        return $this->service->replaceItems($command->priceListId, $command->items);
    }
}
