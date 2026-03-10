<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\AddProcedureCommand;
use App\Modules\Treatment\Application\Data\EncounterProcedureData;
use App\Modules\Treatment\Application\Services\EncounterProcedureService;

final class AddProcedureCommandHandler
{
    public function __construct(
        private readonly EncounterProcedureService $encounterProcedureService,
    ) {}

    public function handle(AddProcedureCommand $command): EncounterProcedureData
    {
        return $this->encounterProcedureService->create($command->encounterId, $command->attributes);
    }
}
