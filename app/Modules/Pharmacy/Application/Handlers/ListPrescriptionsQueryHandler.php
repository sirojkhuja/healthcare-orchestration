<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Queries\ListPrescriptionsQuery;
use App\Modules\Pharmacy\Application\Services\PrescriptionReadService;

final class ListPrescriptionsQueryHandler
{
    public function __construct(
        private readonly PrescriptionReadService $prescriptionReadService,
    ) {}

    /**
     * @return list<PrescriptionData>
     */
    public function handle(ListPrescriptionsQuery $query): array
    {
        return $this->prescriptionReadService->list($query->criteria);
    }
}
