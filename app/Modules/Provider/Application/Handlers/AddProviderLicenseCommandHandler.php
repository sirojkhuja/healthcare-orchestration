<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\AddProviderLicenseCommand;
use App\Modules\Provider\Application\Data\ProviderLicenseData;
use App\Modules\Provider\Application\Services\ProviderLicenseService;

final class AddProviderLicenseCommandHandler
{
    public function __construct(
        private readonly ProviderLicenseService $providerLicenseService,
    ) {}

    public function handle(AddProviderLicenseCommand $command): ProviderLicenseData
    {
        return $this->providerLicenseService->add($command->providerId, $command->attributes);
    }
}
