<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\DeleteRoomCommand;
use App\Modules\TenantManagement\Application\Data\RoomData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class DeleteRoomCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(DeleteRoomCommand $command): RoomData
    {
        return $this->clinicAdministrationService->deleteRoom($command->clinicId, $command->roomId);
    }
}
