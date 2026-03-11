<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Data\InsuranceRuleData;
use App\Modules\Insurance\Application\Queries\ListInsuranceRulesQuery;
use App\Modules\Insurance\Application\Services\InsuranceRuleService;

final readonly class ListInsuranceRulesQueryHandler
{
    public function __construct(
        private InsuranceRuleService $service,
    ) {}

    /**
     * @return list<InsuranceRuleData>
     */
    public function handle(ListInsuranceRulesQuery $query): array
    {
        return $this->service->list(
            query: $query->query,
            payerId: $query->payerId,
            serviceCategory: $query->serviceCategory,
            isActive: $query->isActive,
            limit: $query->limit,
        );
    }
}
