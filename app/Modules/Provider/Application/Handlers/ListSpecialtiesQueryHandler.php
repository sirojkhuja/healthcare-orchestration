<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\SpecialtyData;
use App\Modules\Provider\Application\Queries\ListSpecialtiesQuery;
use App\Modules\Provider\Application\Services\SpecialtyCatalogService;

final class ListSpecialtiesQueryHandler
{
    public function __construct(
        private readonly SpecialtyCatalogService $specialtyCatalogService,
    ) {}

    /**
     * @return list<SpecialtyData>
     */
    public function handle(ListSpecialtiesQuery $query): array
    {
        return $this->specialtyCatalogService->listCatalog();
    }
}
