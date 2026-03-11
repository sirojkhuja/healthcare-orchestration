<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Queries\GetClaimQuery;
use App\Modules\Insurance\Application\Services\ClaimReadService;

final readonly class GetClaimQueryHandler
{
    public function __construct(
        private ClaimReadService $service,
    ) {}

    public function handle(GetClaimQuery $query): ClaimData
    {
        return $this->service->get($query->claimId);
    }
}
