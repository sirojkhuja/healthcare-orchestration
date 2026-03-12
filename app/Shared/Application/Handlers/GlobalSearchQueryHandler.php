<?php

namespace App\Shared\Application\Handlers;

use App\Shared\Application\Data\GlobalSearchResultSetData;
use App\Shared\Application\Queries\GlobalSearchQuery;
use App\Shared\Application\Services\GlobalSearchService;

final class GlobalSearchQueryHandler
{
    public function __construct(
        private readonly GlobalSearchService $globalSearchService,
    ) {}

    public function handle(GlobalSearchQuery $query): GlobalSearchResultSetData
    {
        return $this->globalSearchService->search($query->criteria);
    }
}
