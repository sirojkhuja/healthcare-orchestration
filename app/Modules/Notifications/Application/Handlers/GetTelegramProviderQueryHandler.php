<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Data\TelegramProviderSettingsData;
use App\Modules\Notifications\Application\Queries\GetTelegramProviderQuery;
use App\Modules\Notifications\Application\Services\TelegramProviderSettingsService;

final class GetTelegramProviderQueryHandler
{
    public function __construct(
        private readonly TelegramProviderSettingsService $telegramProviderSettingsService,
    ) {}

    public function handle(GetTelegramProviderQuery $query): TelegramProviderSettingsData
    {
        return $this->telegramProviderSettingsService->get();
    }
}
