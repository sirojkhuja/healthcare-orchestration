<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\EncounterDiagnosisData;
use App\Modules\Treatment\Application\Queries\ListDiagnosesQuery;
use App\Modules\Treatment\Application\Services\EncounterDiagnosisService;

final class ListDiagnosesQueryHandler
{
    public function __construct(
        private readonly EncounterDiagnosisService $encounterDiagnosisService,
    ) {}

    /**
     * @return list<EncounterDiagnosisData>
     */
    public function handle(ListDiagnosesQuery $query): array
    {
        return $this->encounterDiagnosisService->list($query->encounterId);
    }
}
