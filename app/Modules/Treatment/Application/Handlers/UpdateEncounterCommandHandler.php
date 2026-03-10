<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\UpdateEncounterCommand;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Services\EncounterAdministrationService;

final class UpdateEncounterCommandHandler
{
    public function __construct(
        private readonly EncounterAdministrationService $encounterAdministrationService,
    ) {}

    public function handle(UpdateEncounterCommand $command): EncounterData
    {
        return $this->encounterAdministrationService->update($command->encounterId, $command->attributes);
    }
}
