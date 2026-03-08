<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class DeleteUserCommand
{
    public function __construct(
        public string $userId,
    ) {}
}
