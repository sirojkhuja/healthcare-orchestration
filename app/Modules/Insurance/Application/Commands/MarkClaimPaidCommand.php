<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class MarkClaimPaidCommand
{
    public function __construct(
        public string $claimId,
        public string $paidAmount,
        public string $reason,
        public string $sourceEvidence,
    ) {}
}
