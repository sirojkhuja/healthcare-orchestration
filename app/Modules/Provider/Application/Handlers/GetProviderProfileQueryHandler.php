<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderProfileViewData;
use App\Modules\Provider\Application\Queries\GetProviderProfileQuery;
use App\Modules\Provider\Application\Services\ProviderProfileService;

final class GetProviderProfileQueryHandler
{
    public function __construct(
        private readonly ProviderProfileService $providerProfileService,
    ) {}

    public function handle(GetProviderProfileQuery $query): ProviderProfileViewData
    {
        return $this->providerProfileService->get($query->providerId);
    }
}
