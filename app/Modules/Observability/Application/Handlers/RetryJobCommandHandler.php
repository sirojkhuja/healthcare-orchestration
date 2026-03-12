<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\RetryJobCommand;
use App\Modules\Observability\Application\Data\JobRetryData;
use App\Modules\Observability\Application\Services\JobAdministrationService;

final class RetryJobCommandHandler
{
    public function __construct(private readonly JobAdministrationService $jobAdministrationService) {}

    public function handle(RetryJobCommand $command): JobRetryData
    {
        return $this->jobAdministrationService->retry($command->jobId);
    }
}
