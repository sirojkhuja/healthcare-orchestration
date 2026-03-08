<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class AdminResetPasswordCommand
{
    public function __construct(
        public string $userId,
        public string $password,
    ) {}
}
