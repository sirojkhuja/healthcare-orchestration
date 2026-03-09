<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\CreateProviderCommand;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Services\ProviderAdministrationService;

final class CreateProviderCommandHandler
{
    public function __construct(
        private readonly ProviderAdministrationService $providerAdministrationService,
    ) {}

    public function handle(CreateProviderCommand $command): ProviderData
    {
        return $this->providerAdministrationService->create($command->attributes);
    }
}
