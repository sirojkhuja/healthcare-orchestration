<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\ApproveClaimCommand;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Services\ClaimWorkflowService;

final readonly class ApproveClaimCommandHandler
{
    public function __construct(
        private ClaimWorkflowService $service,
    ) {}

    public function handle(ApproveClaimCommand $command): ClaimData
    {
        return $this->service->approve(
            claimId: $command->claimId,
            approvedAmount: $command->approvedAmount,
            reason: $command->reason,
            sourceEvidence: $command->sourceEvidence,
        );
    }
}
