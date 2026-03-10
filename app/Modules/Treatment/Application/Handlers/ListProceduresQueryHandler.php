<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\EncounterProcedureData;
use App\Modules\Treatment\Application\Queries\ListProceduresQuery;
use App\Modules\Treatment\Application\Services\EncounterProcedureService;

final class ListProceduresQueryHandler
{
    public function __construct(
        private readonly EncounterProcedureService $encounterProcedureService,
    ) {}

    /**
     * @return list<EncounterProcedureData>
     */
    public function handle(ListProceduresQuery $query): array
    {
        return $this->encounterProcedureService->list($query->encounterId);
    }
}
