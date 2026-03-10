<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Queries\SearchPrescriptionsQuery;
use App\Modules\Pharmacy\Application\Services\PrescriptionReadService;

final class SearchPrescriptionsQueryHandler
{
    public function __construct(
        private readonly PrescriptionReadService $prescriptionReadService,
    ) {}

    /**
     * @return list<PrescriptionData>
     */
    public function handle(SearchPrescriptionsQuery $query): array
    {
        return $this->prescriptionReadService->search($query->criteria);
    }
}
