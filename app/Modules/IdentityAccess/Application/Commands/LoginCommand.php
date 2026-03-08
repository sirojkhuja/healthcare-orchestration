<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
