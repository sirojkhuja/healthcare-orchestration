<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\UpdateBillableServiceCommand;
use App\Modules\Billing\Application\Data\BillableServiceData;
use App\Modules\Billing\Application\Services\BillableServiceCatalogService;

final readonly class UpdateBillableServiceCommandHandler
{
    public function __construct(private BillableServiceCatalogService $service) {}

    public function handle(UpdateBillableServiceCommand $command): BillableServiceData
    {
        return $this->service->update($command->serviceId, $command->attributes);
    }
}
