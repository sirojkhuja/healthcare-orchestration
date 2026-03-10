<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\PrescriptionExportData;
use App\Modules\Pharmacy\Application\Queries\ExportPrescriptionsQuery;
use App\Modules\Pharmacy\Application\Services\PrescriptionReadService;

final class ExportPrescriptionsQueryHandler
{
    public function __construct(
        private readonly PrescriptionReadService $prescriptionReadService,
    ) {}

    public function handle(ExportPrescriptionsQuery $query): PrescriptionExportData
    {
        return $this->prescriptionReadService->export($query->criteria, $query->format);
    }
}
