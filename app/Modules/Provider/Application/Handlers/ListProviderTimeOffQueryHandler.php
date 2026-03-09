<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Data\ProviderTimeOffData;
use App\Modules\Provider\Application\Queries\ListProviderTimeOffQuery;
use App\Modules\Provider\Application\Services\ProviderScheduleService;

final class ListProviderTimeOffQueryHandler
{
    public function __construct(
        private readonly ProviderScheduleService $providerScheduleService,
    ) {}

    /**
     * @return list<ProviderTimeOffData>
     */
    public function handle(ListProviderTimeOffQuery $query): array
    {
        return $this->providerScheduleService->listTimeOff($query->providerId);
    }
}
