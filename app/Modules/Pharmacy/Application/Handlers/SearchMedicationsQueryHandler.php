<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Queries\SearchMedicationsQuery;
use App\Modules\Pharmacy\Application\Services\MedicationCatalogService;

final class SearchMedicationsQueryHandler
{
    public function __construct(
        private readonly MedicationCatalogService $medicationCatalogService,
    ) {}

    /**
     * @return list<MedicationData>
     */
    public function handle(SearchMedicationsQuery $query): array
    {
        return $this->medicationCatalogService->search($query->criteria);
    }
}
