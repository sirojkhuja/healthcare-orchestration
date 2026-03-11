<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class SubmitClaimCommand
{
    public function __construct(
        public string $claimId,
    ) {}
}
