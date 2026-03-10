<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\DeletePriceListCommand;
use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Services\PriceListCatalogService;

final readonly class DeletePriceListCommandHandler
{
    public function __construct(private PriceListCatalogService $service) {}

    public function handle(DeletePriceListCommand $command): PriceListData
    {
        return $this->service->delete($command->priceListId);
    }
}
