<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\UpdateRoomCommand;
use App\Modules\TenantManagement\Application\Data\RoomData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class UpdateRoomCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(UpdateRoomCommand $command): RoomData
    {
        return $this->clinicAdministrationService->updateRoom(
            $command->clinicId,
            $command->roomId,
            $command->attributes,
        );
    }
}
