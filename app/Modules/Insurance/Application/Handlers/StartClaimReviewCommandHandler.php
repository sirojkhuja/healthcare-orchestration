<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\StartClaimReviewCommand;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Services\ClaimWorkflowService;

final readonly class StartClaimReviewCommandHandler
{
    public function __construct(
        private ClaimWorkflowService $service,
    ) {}

    public function handle(StartClaimReviewCommand $command): ClaimData
    {
        return $this->service->startReview($command->claimId, $command->reason, $command->sourceEvidence);
    }
}
