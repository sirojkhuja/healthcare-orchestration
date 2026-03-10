<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\CreateBillableServiceCommand;
use App\Modules\Billing\Application\Data\BillableServiceData;
use App\Modules\Billing\Application\Services\BillableServiceCatalogService;

final readonly class CreateBillableServiceCommandHandler
{
    public function __construct(private BillableServiceCatalogService $service) {}

    public function handle(CreateBillableServiceCommand $command): BillableServiceData
    {
        return $this->service->create($command->attributes);
    }
}
