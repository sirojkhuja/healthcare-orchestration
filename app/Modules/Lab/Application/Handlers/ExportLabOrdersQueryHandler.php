<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Data\LabOrderExportData;
use App\Modules\Lab\Application\Queries\ExportLabOrdersQuery;
use App\Modules\Lab\Application\Services\LabOrderReadService;

final class ExportLabOrdersQueryHandler
{
    public function __construct(
        private readonly LabOrderReadService $labOrderReadService,
    ) {}

    public function handle(ExportLabOrdersQuery $query): LabOrderExportData
    {
        return $this->labOrderReadService->export($query->criteria, $query->format);
    }
}
