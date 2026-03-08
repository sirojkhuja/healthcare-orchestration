<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class DeactivateUserCommand
{
    public function __construct(
        public string $userId,
    ) {}
}
