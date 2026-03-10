<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Queries\GetMedicationQuery;
use App\Modules\Pharmacy\Application\Services\MedicationCatalogService;

final class GetMedicationQueryHandler
{
    public function __construct(
        private readonly MedicationCatalogService $medicationCatalogService,
    ) {}

    public function handle(GetMedicationQuery $query): MedicationData
    {
        return $this->medicationCatalogService->get($query->medicationId);
    }
}
