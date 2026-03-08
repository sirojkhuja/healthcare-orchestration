<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class ActivateUserCommand
{
    public function __construct(
        public string $userId,
    ) {}
}
