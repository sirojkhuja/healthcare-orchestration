<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public ?string $status = null,
    ) {}
}
