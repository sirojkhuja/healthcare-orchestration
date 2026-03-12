<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Data\EmailProviderSettingsData;
use App\Modules\Notifications\Application\Queries\GetEmailProviderQuery;
use App\Modules\Notifications\Application\Services\EmailProviderSettingsService;

final class GetEmailProviderQueryHandler
{
    public function __construct(
        private readonly EmailProviderSettingsService $emailProviderSettingsService,
    ) {}

    public function handle(GetEmailProviderQuery $query): EmailProviderSettingsData
    {
        return $this->emailProviderSettingsService->get();
    }
}
