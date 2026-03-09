<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderWorkHoursData;
use App\Modules\Provider\Application\Queries\GetProviderWorkHoursQuery;
use App\Modules\Provider\Application\Services\ProviderScheduleService;

final class GetProviderWorkHoursQueryHandler
{
    public function __construct(
        private readonly ProviderScheduleService $providerScheduleService,
    ) {}

    public function handle(GetProviderWorkHoursQuery $query): ProviderWorkHoursData
    {
        return $this->providerScheduleService->getWorkHours($query->providerId);
    }
}
