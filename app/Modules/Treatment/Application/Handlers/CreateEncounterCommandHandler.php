<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\CreateEncounterCommand;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Services\EncounterAdministrationService;

final class CreateEncounterCommandHandler
{
    public function __construct(
        private readonly EncounterAdministrationService $encounterAdministrationService,
    ) {}

    public function handle(CreateEncounterCommand $command): EncounterData
    {
        return $this->encounterAdministrationService->create($command->attributes);
    }
}
