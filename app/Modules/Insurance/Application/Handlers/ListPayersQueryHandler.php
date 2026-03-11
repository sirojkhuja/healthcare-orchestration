<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Data\PayerData;
use App\Modules\Insurance\Application\Queries\ListPayersQuery;
use App\Modules\Insurance\Application\Services\PayerCatalogService;

final readonly class ListPayersQueryHandler
{
    public function __construct(
        private PayerCatalogService $service,
    ) {}

    /**
     * @return list<PayerData>
     */
    public function handle(ListPayersQuery $query): array
    {
        return $this->service->list(
            query: $query->query,
            insuranceCode: $query->insuranceCode,
            isActive: $query->isActive,
            limit: $query->limit,
        );
    }
}
