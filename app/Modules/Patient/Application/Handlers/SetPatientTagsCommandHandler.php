<?php

namespace App\Modules\Patient\Application\Handlers;

use App\Modules\Patient\Application\Commands\SetPatientTagsCommand;
use App\Modules\Patient\Application\Data\PatientTagListData;
use App\Modules\Patient\Application\Services\PatientTagService;

final class SetPatientTagsCommandHandler
{
    public function __construct(
        private readonly PatientTagService $patientTagService,
    ) {}

    public function handle(SetPatientTagsCommand $command): PatientTagListData
    {
        return $this->patientTagService->replace($command->patientId, $command->tags);
    }
}
