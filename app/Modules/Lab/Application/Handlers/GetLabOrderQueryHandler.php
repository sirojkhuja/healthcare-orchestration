<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Queries\GetLabOrderQuery;
use App\Modules\Lab\Application\Services\LabOrderAdministrationService;

final class GetLabOrderQueryHandler
{
    public function __construct(
        private readonly LabOrderAdministrationService $labOrderAdministrationService,
    ) {}

    public function handle(GetLabOrderQuery $query): LabOrderData
    {
        return $this->labOrderAdministrationService->get($query->orderId);
    }
}
