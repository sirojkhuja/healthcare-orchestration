<?php

namespace App\Modules\TenantManagement\Application\Handlers;

use App\Modules\TenantManagement\Application\Commands\CreateRoomCommand;
use App\Modules\TenantManagement\Application\Data\RoomData;
use App\Modules\TenantManagement\Application\Services\ClinicAdministrationService;

final class CreateRoomCommandHandler
{
    public function __construct(
        private readonly ClinicAdministrationService $clinicAdministrationService,
    ) {}

    public function handle(CreateRoomCommand $command): RoomData
    {
        return $this->clinicAdministrationService->createRoom($command->clinicId, $command->attributes);
    }
}
