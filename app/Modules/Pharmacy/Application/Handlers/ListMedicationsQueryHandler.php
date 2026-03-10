<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Queries\ListMedicationsQuery;
use App\Modules\Pharmacy\Application\Services\MedicationCatalogService;

final class ListMedicationsQueryHandler
{
    public function __construct(
        private readonly MedicationCatalogService $medicationCatalogService,
    ) {}

    /**
     * @return list<MedicationData>
     */
    public function handle(ListMedicationsQuery $query): array
    {
        return $this->medicationCatalogService->list($query->criteria);
    }
}
