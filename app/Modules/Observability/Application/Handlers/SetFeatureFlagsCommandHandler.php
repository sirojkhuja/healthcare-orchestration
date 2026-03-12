<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\SetFeatureFlagsCommand;
use App\Modules\Observability\Application\Data\FeatureFlagData;
use App\Modules\Observability\Application\Services\FeatureFlagService;

final class SetFeatureFlagsCommandHandler
{
    public function __construct(private readonly FeatureFlagService $featureFlagService) {}

    /**
     * @return list<FeatureFlagData>
     */
    public function handle(SetFeatureFlagsCommand $command): array
    {
        return $this->featureFlagService->set($command->flags);
    }
}
