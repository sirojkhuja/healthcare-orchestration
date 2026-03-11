<?php

namespace App\Modules\Insurance\Application\Queries;

final readonly class ListClaimAttachmentsQuery
{
    public function __construct(
        public string $claimId,
    ) {}
}
