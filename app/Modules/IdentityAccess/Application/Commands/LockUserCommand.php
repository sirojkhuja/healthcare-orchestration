<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class LockUserCommand
{
    public function __construct(
        public string $userId,
    ) {}
}
