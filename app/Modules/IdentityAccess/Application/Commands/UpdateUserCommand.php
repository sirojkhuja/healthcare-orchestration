<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class UpdateUserCommand
{
    public function __construct(
        public string $userId,
        public ?string $name,
        public ?string $email,
    ) {}
}
