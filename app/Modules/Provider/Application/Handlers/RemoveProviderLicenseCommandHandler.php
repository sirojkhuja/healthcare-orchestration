<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\RemoveProviderLicenseCommand;
use App\Modules\Provider\Application\Data\ProviderLicenseData;
use App\Modules\Provider\Application\Services\ProviderLicenseService;

final class RemoveProviderLicenseCommandHandler
{
    public function __construct(
        private readonly ProviderLicenseService $providerLicenseService,
    ) {}

    public function handle(RemoveProviderLicenseCommand $command): ProviderLicenseData
    {
        return $this->providerLicenseService->remove($command->providerId, $command->licenseId);
    }
}
