<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderSpecialtyData;
use App\Modules\Provider\Application\Queries\ListProviderSpecialtiesQuery;
use App\Modules\Provider\Application\Services\SpecialtyCatalogService;

final class ListProviderSpecialtiesQueryHandler
{
    public function __construct(
        private readonly SpecialtyCatalogService $specialtyCatalogService,
    ) {}

    /**
     * @return list<ProviderSpecialtyData>
     */
    public function handle(ListProviderSpecialtiesQuery $query): array
    {
        return $this->specialtyCatalogService->listProviderSpecialties($query->providerId);
    }
}
