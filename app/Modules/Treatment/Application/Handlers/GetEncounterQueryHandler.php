<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Queries\GetEncounterQuery;
use App\Modules\Treatment\Application\Services\EncounterAdministrationService;

final class GetEncounterQueryHandler
{
    public function __construct(
        private readonly EncounterAdministrationService $encounterAdministrationService,
    ) {}

    public function handle(GetEncounterQuery $query): EncounterData
    {
        return $this->encounterAdministrationService->get($query->encounterId);
    }
}
