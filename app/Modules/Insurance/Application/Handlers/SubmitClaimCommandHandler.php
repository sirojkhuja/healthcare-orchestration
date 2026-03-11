<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\SubmitClaimCommand;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Services\ClaimWorkflowService;

final readonly class SubmitClaimCommandHandler
{
    public function __construct(
        private ClaimWorkflowService $service,
    ) {}

    public function handle(SubmitClaimCommand $command): ClaimData
    {
        return $this->service->submit($command->claimId);
    }
}
