<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderLicenseData;
use App\Modules\Provider\Application\Queries\ListProviderLicensesQuery;
use App\Modules\Provider\Application\Services\ProviderLicenseService;

final class ListProviderLicensesQueryHandler
{
    public function __construct(
        private readonly ProviderLicenseService $providerLicenseService,
    ) {}

    /**
     * @return list<ProviderLicenseData>
     */
    public function handle(ListProviderLicensesQuery $query): array
    {
        return $this->providerLicenseService->list($query->providerId);
    }
}
