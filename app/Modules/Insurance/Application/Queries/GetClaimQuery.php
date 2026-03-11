<?php

namespace App\Modules\Insurance\Application\Queries;

final readonly class GetClaimQuery
{
    public function __construct(
        public string $claimId,
    ) {}
}
