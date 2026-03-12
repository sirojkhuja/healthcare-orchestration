<?php

namespace App\Shared\Application\Handlers;

use App\Shared\Application\Data\ReferenceEntryData;
use App\Shared\Application\Queries\ListProcedureCodesQuery;
use App\Shared\Application\Services\ReferenceCatalogService;

final class ListProcedureCodesQueryHandler
{
    public function __construct(
        private readonly ReferenceCatalogService $referenceCatalogService,
    ) {}

    /**
     * @return list<ReferenceEntryData>
     */
    public function handle(ListProcedureCodesQuery $query): array
    {
        return $this->referenceCatalogService->list('procedure_codes', $query->query, $query->limit);
    }
}
