<?php

namespace App\Modules\Billing\Application\Handlers;

use App\Modules\Billing\Application\Commands\DeleteBillableServiceCommand;
use App\Modules\Billing\Application\Data\BillableServiceData;
use App\Modules\Billing\Application\Services\BillableServiceCatalogService;

final readonly class DeleteBillableServiceCommandHandler
{
    public function __construct(private BillableServiceCatalogService $service) {}

    public function handle(DeleteBillableServiceCommand $command): BillableServiceData
    {
        return $this->service->delete($command->serviceId);
    }
}
