<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\AddDiagnosisCommand;
use App\Modules\Treatment\Application\Data\EncounterDiagnosisData;
use App\Modules\Treatment\Application\Services\EncounterDiagnosisService;

final class AddDiagnosisCommandHandler
{
    public function __construct(
        private readonly EncounterDiagnosisService $encounterDiagnosisService,
    ) {}

    public function handle(AddDiagnosisCommand $command): EncounterDiagnosisData
    {
        return $this->encounterDiagnosisService->create($command->encounterId, $command->attributes);
    }
}
