<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class UpdateRoleCommand
{
    public function __construct(
        public string $roleId,
        public ?string $name,
        public bool $descriptionProvided,
        public ?string $description,
    ) {}
}
