<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderGroupData;
use App\Modules\Provider\Application\Queries\ListProviderGroupsQuery;
use App\Modules\Provider\Application\Services\ProviderGroupService;

final class ListProviderGroupsQueryHandler
{
    public function __construct(
        private readonly ProviderGroupService $providerGroupService,
    ) {}

    /**
     * @return list<ProviderGroupData>
     */
    public function handle(ListProviderGroupsQuery $query): array
    {
        return $this->providerGroupService->list();
    }
}
