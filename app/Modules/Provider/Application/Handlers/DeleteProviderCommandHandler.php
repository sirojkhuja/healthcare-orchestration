<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\DeleteProviderCommand;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Provider\Application\Services\ProviderAdministrationService;

final class DeleteProviderCommandHandler
{
    public function __construct(
        private readonly ProviderAdministrationService $providerAdministrationService,
    ) {}

    public function handle(DeleteProviderCommand $command): ProviderData
    {
        return $this->providerAdministrationService->delete($command->providerId);
    }
}
