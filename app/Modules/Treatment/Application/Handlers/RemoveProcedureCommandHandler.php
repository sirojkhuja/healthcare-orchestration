<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\RemoveProcedureCommand;
use App\Modules\Treatment\Application\Data\EncounterProcedureData;
use App\Modules\Treatment\Application\Services\EncounterProcedureService;

final class RemoveProcedureCommandHandler
{
    public function __construct(
        private readonly EncounterProcedureService $encounterProcedureService,
    ) {}

    public function handle(RemoveProcedureCommand $command): EncounterProcedureData
    {
        return $this->encounterProcedureService->delete($command->encounterId, $command->procedureId);
    }
}
