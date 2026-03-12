<?php

namespace App\Shared\Application\Handlers;

use App\Shared\Application\Data\ReferenceEntryData;
use App\Shared\Application\Queries\ListDiagnosisCodesQuery;
use App\Shared\Application\Services\ReferenceCatalogService;

final class ListDiagnosisCodesQueryHandler
{
    public function __construct(
        private readonly ReferenceCatalogService $referenceCatalogService,
    ) {}

    /**
     * @return list<ReferenceEntryData>
     */
    public function handle(ListDiagnosisCodesQuery $query): array
    {
        return $this->referenceCatalogService->list('diagnosis_codes', $query->query, $query->limit);
    }
}
