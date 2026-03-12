<?php

namespace App\Shared\Application\Handlers;

use App\Shared\Application\Data\ReferenceEntryData;
use App\Shared\Application\Queries\ListCurrenciesQuery;
use App\Shared\Application\Services\ReferenceCatalogService;

final class ListCurrenciesQueryHandler
{
    public function __construct(
        private readonly ReferenceCatalogService $referenceCatalogService,
    ) {}

    /**
     * @return list<ReferenceEntryData>
     */
    public function handle(ListCurrenciesQuery $query): array
    {
        return $this->referenceCatalogService->list('currencies', $query->query, $query->limit);
    }
}
