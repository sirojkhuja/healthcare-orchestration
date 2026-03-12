<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\UpdateRateLimitsCommand;
use App\Modules\Observability\Application\Data\RateLimitData;
use App\Modules\Observability\Application\Services\RateLimitService;

final class UpdateRateLimitsCommandHandler
{
    public function __construct(private readonly RateLimitService $rateLimitService) {}

    /**
     * @return list<RateLimitData>
     */
    public function handle(UpdateRateLimitsCommand $command): array
    {
        return $this->rateLimitService->update($command->limits);
    }
}
