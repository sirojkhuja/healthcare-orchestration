<?php

namespace App\Shared\Application\Handlers;

use App\Shared\Application\Data\ReferenceEntryData;
use App\Shared\Application\Queries\ListCountriesQuery;
use App\Shared\Application\Services\ReferenceCatalogService;

final class ListCountriesQueryHandler
{
    public function __construct(
        private readonly ReferenceCatalogService $referenceCatalogService,
    ) {}

    /**
     * @return list<ReferenceEntryData>
     */
    public function handle(ListCountriesQuery $query): array
    {
        return $this->referenceCatalogService->list('countries', $query->query, $query->limit);
    }
}
