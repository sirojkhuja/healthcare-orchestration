<?php

namespace App\Shared\Application\Handlers;

use App\Shared\Application\Data\ReferenceEntryData;
use App\Shared\Application\Queries\ListInsuranceCodesQuery;
use App\Shared\Application\Services\ReferenceCatalogService;

final class ListInsuranceCodesQueryHandler
{
    public function __construct(
        private readonly ReferenceCatalogService $referenceCatalogService,
    ) {}

    /**
     * @return list<ReferenceEntryData>
     */
    public function handle(ListInsuranceCodesQuery $query): array
    {
        return $this->referenceCatalogService->list('insurance_codes', $query->query, $query->limit);
    }
}
