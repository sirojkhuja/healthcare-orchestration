<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class DeregisterDeviceCommand
{
    public function __construct(
        public string $deviceId,
    ) {}
}
