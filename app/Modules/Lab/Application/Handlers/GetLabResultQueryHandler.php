<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Data\LabResultData;
use App\Modules\Lab\Application\Queries\GetLabResultQuery;
use App\Modules\Lab\Application\Services\LabOrderReadService;

final class GetLabResultQueryHandler
{
    public function __construct(
        private readonly LabOrderReadService $labOrderReadService,
    ) {}

    public function handle(GetLabResultQuery $query): LabResultData
    {
        return $this->labOrderReadService->showResult($query->orderId, $query->resultId);
    }
}
