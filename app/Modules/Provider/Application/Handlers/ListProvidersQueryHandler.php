<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Queries\ListProvidersQuery;
use App\Modules\Provider\Application\Services\ProviderAdministrationService;

final class ListProvidersQueryHandler
{
    public function __construct(
        private readonly ProviderAdministrationService $providerAdministrationService,
    ) {}

    /**
     * @return list<ProviderData>
     */
    public function handle(ListProvidersQuery $query): array
    {
        return $this->providerAdministrationService->list();
    }
}
