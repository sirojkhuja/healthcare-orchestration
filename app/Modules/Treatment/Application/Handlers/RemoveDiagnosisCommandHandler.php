<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\RemoveDiagnosisCommand;
use App\Modules\Treatment\Application\Data\EncounterDiagnosisData;
use App\Modules\Treatment\Application\Services\EncounterDiagnosisService;

final class RemoveDiagnosisCommandHandler
{
    public function __construct(
        private readonly EncounterDiagnosisService $encounterDiagnosisService,
    ) {}

    public function handle(RemoveDiagnosisCommand $command): EncounterDiagnosisData
    {
        return $this->encounterDiagnosisService->delete($command->encounterId, $command->diagnosisId);
    }
}
