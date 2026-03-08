<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\DeviceRepository;
use App\Modules\IdentityAccess\Application\Data\RegisteredDeviceData;
use App\Modules\IdentityAccess\Application\Queries\ListDevicesQuery;

final class ListDevicesQueryHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly DeviceRepository $deviceRepository,
    ) {}

    /**
     * @return list<RegisteredDeviceData>
     */
    public function handle(ListDevicesQuery $query): array
    {
        $current = $this->authenticatedRequestContext->current();

        return $this->deviceRepository->listForUser($current->user->id);
    }
}
