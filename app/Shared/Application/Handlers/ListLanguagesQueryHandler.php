<?php

namespace App\Shared\Application\Handlers;

use App\Shared\Application\Data\ReferenceEntryData;
use App\Shared\Application\Queries\ListLanguagesQuery;
use App\Shared\Application\Services\ReferenceCatalogService;

final class ListLanguagesQueryHandler
{
    public function __construct(
        private readonly ReferenceCatalogService $referenceCatalogService,
    ) {}

    /**
     * @return list<ReferenceEntryData>
     */
    public function handle(ListLanguagesQuery $query): array
    {
        return $this->referenceCatalogService->list('languages', $query->query, $query->limit);
    }
}
