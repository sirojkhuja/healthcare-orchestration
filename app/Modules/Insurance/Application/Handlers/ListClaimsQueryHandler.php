<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Queries\ListClaimsQuery;
use App\Modules\Insurance\Application\Services\ClaimReadService;

final readonly class ListClaimsQueryHandler
{
    public function __construct(
        private ClaimReadService $service,
    ) {}

    /**
     * @return list<ClaimData>
     */
    public function handle(ListClaimsQuery $query): array
    {
        return $this->service->list($query->criteria);
    }
}
