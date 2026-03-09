<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\UpdateProviderTimeOffCommand;
use App\Modules\Provider\Application\Data\ProviderTimeOffData;
use App\Modules\Provider\Application\Services\ProviderScheduleService;

final class UpdateProviderTimeOffCommandHandler
{
    public function __construct(
        private readonly ProviderScheduleService $providerScheduleService,
    ) {}

    public function handle(UpdateProviderTimeOffCommand $command): ProviderTimeOffData
    {
        return $this->providerScheduleService->updateTimeOff(
            $command->providerId,
            $command->timeOffId,
            $command->attributes,
        );
    }
}
