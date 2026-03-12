<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\SetEmailProviderCommand;
use App\Modules\Notifications\Application\Data\EmailProviderSettingsData;
use App\Modules\Notifications\Application\Services\EmailProviderSettingsService;

final class SetEmailProviderCommandHandler
{
    public function __construct(
        private readonly EmailProviderSettingsService $emailProviderSettingsService,
    ) {}

    public function handle(SetEmailProviderCommand $command): EmailProviderSettingsData
    {
        return $this->emailProviderSettingsService->update($command->attributes);
    }
}
