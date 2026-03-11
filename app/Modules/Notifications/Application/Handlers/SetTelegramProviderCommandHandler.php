<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\SetTelegramProviderCommand;
use App\Modules\Notifications\Application\Data\TelegramProviderSettingsData;
use App\Modules\Notifications\Application\Services\TelegramProviderSettingsService;

final class SetTelegramProviderCommandHandler
{
    public function __construct(
        private readonly TelegramProviderSettingsService $telegramProviderSettingsService,
    ) {}

    public function handle(SetTelegramProviderCommand $command): TelegramProviderSettingsData
    {
        return $this->telegramProviderSettingsService->update($command->attributes);
    }
}
