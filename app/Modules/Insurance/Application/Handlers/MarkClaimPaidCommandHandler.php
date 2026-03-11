<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\MarkClaimPaidCommand;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Services\ClaimWorkflowService;

final readonly class MarkClaimPaidCommandHandler
{
    public function __construct(
        private ClaimWorkflowService $service,
    ) {}

    public function handle(MarkClaimPaidCommand $command): ClaimData
    {
        return $this->service->markPaid(
            claimId: $command->claimId,
            paidAmount: $command->paidAmount,
            reason: $command->reason,
            sourceEvidence: $command->sourceEvidence,
        );
    }
}
