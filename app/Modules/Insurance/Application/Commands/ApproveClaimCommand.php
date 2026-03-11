<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class ApproveClaimCommand
{
    public function __construct(
        public string $claimId,
        public string $approvedAmount,
        public string $reason,
        public string $sourceEvidence,
    ) {}
}
