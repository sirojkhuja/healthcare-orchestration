<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class CreateRoleCommand
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}
}
