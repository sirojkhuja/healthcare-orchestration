<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Data\SmsRoutingSettingsData;
use App\Modules\Notifications\Application\Queries\ListSmsProvidersQuery;
use App\Modules\Notifications\Application\Services\SmsRoutingPolicyService;

final class ListSmsProvidersQueryHandler
{
    public function __construct(
        private readonly SmsRoutingPolicyService $smsRoutingPolicyService,
    ) {}

    public function handle(ListSmsProvidersQuery $query): SmsRoutingSettingsData
    {
        return $this->smsRoutingPolicyService->list();
    }
}
