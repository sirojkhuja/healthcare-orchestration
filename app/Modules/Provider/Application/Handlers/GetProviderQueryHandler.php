<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Queries\GetProviderQuery;
use App\Modules\Provider\Application\Services\ProviderAdministrationService;

final class GetProviderQueryHandler
{
    public function __construct(
        private readonly ProviderAdministrationService $providerAdministrationService,
    ) {}

    public function handle(GetProviderQuery $query): ProviderData
    {
        return $this->providerAdministrationService->get($query->providerId);
    }
}
