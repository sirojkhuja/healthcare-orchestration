<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use App\Modules\Scheduling\Application\Queries\ListAvailabilityRulesQuery;
use App\Modules\Scheduling\Application\Services\AvailabilityRuleService;

final class ListAvailabilityRulesQueryHandler
{
    public function __construct(
        private readonly AvailabilityRuleService $availabilityRuleService,
    ) {}

    /**
     * @return list<AvailabilityRuleData>
     */
    public function handle(ListAvailabilityRulesQuery $query): array
    {
        return $this->availabilityRuleService->listForProvider($query->providerId);
    }
}
