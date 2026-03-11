<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class StartClaimReviewCommand
{
    public function __construct(
        public string $claimId,
        public string $reason,
        public string $sourceEvidence,
    ) {}
}
