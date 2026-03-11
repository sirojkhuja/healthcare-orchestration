<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\SetSmsProvidersPriorityCommand;
use App\Modules\Notifications\Application\Data\SmsRoutingSettingsData;
use App\Modules\Notifications\Application\Services\SmsRoutingPolicyService;

final class SetSmsProvidersPriorityCommandHandler
{
    public function __construct(
        private readonly SmsRoutingPolicyService $smsRoutingPolicyService,
    ) {}

    public function handle(SetSmsProvidersPriorityCommand $command): SmsRoutingSettingsData
    {
        return $this->smsRoutingPolicyService->update($command->routes);
    }
}
