<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\DeleteProviderTimeOffCommand;
use App\Modules\Provider\Application\Data\ProviderTimeOffData;
use App\Modules\Provider\Application\Services\ProviderScheduleService;

final class DeleteProviderTimeOffCommandHandler
{
    public function __construct(
        private readonly ProviderScheduleService $providerScheduleService,
    ) {}

    public function handle(DeleteProviderTimeOffCommand $command): ProviderTimeOffData
    {
        return $this->providerScheduleService->deleteTimeOff($command->providerId, $command->timeOffId);
    }
}
