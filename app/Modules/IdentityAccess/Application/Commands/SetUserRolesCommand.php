<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class SetUserRolesCommand
{
    /**
     * @param  list<string>  $roleIds
     */
    public function __construct(
        public string $userId,
        public array $roleIds,
    ) {}
}
