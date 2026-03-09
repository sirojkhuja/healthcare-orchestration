<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\UpdateProviderProfileCommand;
use App\Modules\Provider\Application\Data\ProviderProfileViewData;
use App\Modules\Provider\Application\Services\ProviderProfileService;

final class UpdateProviderProfileCommandHandler
{
    public function __construct(
        private readonly ProviderProfileService $providerProfileService,
    ) {}

    public function handle(UpdateProviderProfileCommand $command): ProviderProfileViewData
    {
        return $this->providerProfileService->update($command->providerId, $command->attributes);
    }
}
