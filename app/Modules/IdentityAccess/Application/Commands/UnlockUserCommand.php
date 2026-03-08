<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class UnlockUserCommand
{
    public function __construct(
        public string $userId,
    ) {}
}
