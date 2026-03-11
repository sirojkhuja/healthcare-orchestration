<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class DenyClaimCommand
{
    public function __construct(
        public string $claimId,
        public string $reason,
        public string $sourceEvidence,
    ) {}
}
