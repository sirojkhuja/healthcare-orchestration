<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class DeleteClaimCommand
{
    public function __construct(
        public string $claimId,
    ) {}
}
