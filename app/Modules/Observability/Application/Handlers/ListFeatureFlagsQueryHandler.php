<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\FeatureFlagData;
use App\Modules\Observability\Application\Queries\ListFeatureFlagsQuery;
use App\Modules\Observability\Application\Services\FeatureFlagService;

final class ListFeatureFlagsQueryHandler
{
    public function __construct(private readonly FeatureFlagService $featureFlagService) {}

    /**
     * @return list<FeatureFlagData>
     */
    public function handle(ListFeatureFlagsQuery $query): array
    {
        return $this->featureFlagService->list();
    }
}
