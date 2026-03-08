<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class RegisterDeviceCommand
{
    public function __construct(
        public string $installationId,
        public string $name,
        public string $platform,
        public ?string $pushToken,
        public ?string $appVersion,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
