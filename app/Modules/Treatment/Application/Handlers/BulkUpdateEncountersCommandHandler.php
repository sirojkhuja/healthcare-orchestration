<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\BulkUpdateEncountersCommand;
use App\Modules\Treatment\Application\Data\BulkEncounterUpdateData;
use App\Modules\Treatment\Application\Services\EncounterBulkUpdateService;

final class BulkUpdateEncountersCommandHandler
{
    public function __construct(
        private readonly EncounterBulkUpdateService $encounterBulkUpdateService,
    ) {}

    public function handle(BulkUpdateEncountersCommand $command): BulkEncounterUpdateData
    {
        return $this->encounterBulkUpdateService->update($command->encounterIds, $command->changes);
    }
}
