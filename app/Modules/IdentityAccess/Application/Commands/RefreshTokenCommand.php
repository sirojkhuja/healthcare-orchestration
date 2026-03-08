<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class RefreshTokenCommand
{
    public function __construct(
        public string $refreshToken,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
