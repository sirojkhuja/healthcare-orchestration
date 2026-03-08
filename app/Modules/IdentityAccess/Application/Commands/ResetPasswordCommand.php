<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class ResetPasswordCommand
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {}
}
