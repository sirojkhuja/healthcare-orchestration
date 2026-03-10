<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\UpdatePriceListCommand;
use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Services\PriceListCatalogService;

final readonly class UpdatePriceListCommandHandler
{
    public function __construct(private PriceListCatalogService $service) {}

    public function handle(UpdatePriceListCommand $command): PriceListData
    {
        return $this->service->update($command->priceListId, $command->attributes);
    }
}
