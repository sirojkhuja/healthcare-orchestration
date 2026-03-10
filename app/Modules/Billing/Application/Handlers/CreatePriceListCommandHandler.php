<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\CreatePriceListCommand;
use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Services\PriceListCatalogService;

final readonly class CreatePriceListCommandHandler
{
    public function __construct(private PriceListCatalogService $service) {}

    public function handle(CreatePriceListCommand $command): PriceListData
    {
        return $this->service->create($command->attributes);
    }
}
