<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Queries\ListRoomsQuery;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class ListRoomsQueryHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    /**
     * @return list<\App\Modules\TenantManagement\Application\Data\RoomData>
     */
    public function handle(ListRoomsQuery $query): array
    {
        return $this->clinicAdministrationService->listRooms($query->clinicId);
    }
}
