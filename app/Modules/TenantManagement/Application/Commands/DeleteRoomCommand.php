<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class DeleteRoomCommand
{
    public function __construct(
        public string $clinicId,
        public string $roomId,
    ) {}
}
