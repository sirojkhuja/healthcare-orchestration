<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class LogoutCommand
{
    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
