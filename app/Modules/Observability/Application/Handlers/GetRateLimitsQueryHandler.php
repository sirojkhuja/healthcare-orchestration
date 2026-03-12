<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\RateLimitData;
use App\Modules\Observability\Application\Queries\GetRateLimitsQuery;
use App\Modules\Observability\Application\Services\RateLimitService;

final class GetRateLimitsQueryHandler
{
    public function __construct(private readonly RateLimitService $rateLimitService) {}

    /**
     * @return list<RateLimitData>
     */
    public function handle(GetRateLimitsQuery $query): array
    {
        return $this->rateLimitService->list();
    }
}
