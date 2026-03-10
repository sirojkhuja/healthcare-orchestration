<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Queries\ListEncountersQuery;
use App\Modules\Treatment\Application\Services\EncounterReadService;

final class ListEncountersQueryHandler
{
    public function __construct(
        private readonly EncounterReadService $encounterReadService,
    ) {}

    /**
     * @return list<EncounterData>
     */
    public function handle(ListEncountersQuery $query): array
    {
        return $this->encounterReadService->list($query->criteria);
    }
}
