<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Queries\SearchClaimsQuery;
use App\Modules\Insurance\Application\Services\ClaimReadService;

final readonly class SearchClaimsQueryHandler
{
    public function __construct(
        private ClaimReadService $service,
    ) {}

    /**
     * @return list<ClaimData>
     */
    public function handle(SearchClaimsQuery $query): array
    {
        return $this->service->search($query->criteria);
    }
}
