<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\EncounterExportData;
use App\Modules\Treatment\Application\Queries\ExportEncountersQuery;
use App\Modules\Treatment\Application\Services\EncounterReadService;

final class ExportEncountersQueryHandler
{
    public function __construct(
        private readonly EncounterReadService $encounterReadService,
    ) {}

    public function handle(ExportEncountersQuery $query): EncounterExportData
    {
        return $this->encounterReadService->export($query);
    }
}
