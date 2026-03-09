<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\UpdateProviderCommand;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Services\ProviderAdministrationService;

final class UpdateProviderCommandHandler
{
    public function __construct(
        private readonly ProviderAdministrationService $providerAdministrationService,
    ) {}

    public function handle(UpdateProviderCommand $command): ProviderData
    {
        return $this->providerAdministrationService->update($command->providerId, $command->attributes);
    }
}
