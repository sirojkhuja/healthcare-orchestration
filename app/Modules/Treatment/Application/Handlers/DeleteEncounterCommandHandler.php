<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\DeleteEncounterCommand;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Services\EncounterAdministrationService;

final class DeleteEncounterCommandHandler
{
    public function __construct(
        private readonly EncounterAdministrationService $encounterAdministrationService,
    ) {}

    public function handle(DeleteEncounterCommand $command): EncounterData
    {
        return $this->encounterAdministrationService->delete($command->encounterId);
    }
}
