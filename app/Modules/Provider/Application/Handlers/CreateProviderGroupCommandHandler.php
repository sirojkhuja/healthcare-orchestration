<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\CreateProviderGroupCommand;
use App\Modules\Provider\Application\Data\ProviderGroupData;
use App\Modules\Provider\Application\Services\ProviderGroupService;

final class CreateProviderGroupCommandHandler
{
    public function __construct(
        private readonly ProviderGroupService $providerGroupService,
    ) {}

    public function handle(CreateProviderGroupCommand $command): ProviderGroupData
    {
        return $this->providerGroupService->create($command->attributes);
    }
}
