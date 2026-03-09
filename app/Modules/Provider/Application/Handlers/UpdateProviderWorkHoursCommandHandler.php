<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\UpdateProviderWorkHoursCommand;
use App\Modules\Provider\Application\Data\ProviderWorkHoursData;
use App\Modules\Provider\Application\Services\ProviderScheduleService;

final class UpdateProviderWorkHoursCommandHandler
{
    public function __construct(
        private readonly ProviderScheduleService $providerScheduleService,
    ) {}

    public function handle(UpdateProviderWorkHoursCommand $command): ProviderWorkHoursData
    {
        return $this->providerScheduleService->updateWorkHours($command->providerId, $command->workHours);
    }
}
