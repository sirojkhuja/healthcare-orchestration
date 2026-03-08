<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class DeleteRoleCommand
{
    public function __construct(
        public string $roleId,
    ) {}
}
