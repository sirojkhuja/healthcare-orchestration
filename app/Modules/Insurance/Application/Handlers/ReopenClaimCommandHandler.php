<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\ReopenClaimCommand;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Services\ClaimWorkflowService;

final readonly class ReopenClaimCommandHandler
{
    public function __construct(
        private ClaimWorkflowService $service,
    ) {}

    public function handle(ReopenClaimCommand $command): ClaimData
    {
        return $this->service->reopen($command->claimId, $command->reason, $command->sourceEvidence);
    }
}
