<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\CreateProviderTimeOffCommand;
use App\Modules\Provider\Application\Data\ProviderTimeOffData;
use App\Modules\Provider\Application\Services\ProviderScheduleService;

final class CreateProviderTimeOffCommandHandler
{
    public function __construct(
        private readonly ProviderScheduleService $providerScheduleService,
    ) {}

    public function handle(CreateProviderTimeOffCommand $command): ProviderTimeOffData
    {
        return $this->providerScheduleService->createTimeOff($command->providerId, $command->attributes);
    }
}
