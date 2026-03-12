<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Queries\SearchProvidersQuery;
use App\Modules\Provider\Application\Services\ProviderReadService;

final class SearchProvidersQueryHandler
{
    public function __construct(
        private readonly ProviderReadService $providerReadService,
    ) {}

    /**
     * @return list<ProviderData>
     */
    public function handle(SearchProvidersQuery $query): array
    {
        return $this->providerReadService->search($query->criteria);
    }
}
