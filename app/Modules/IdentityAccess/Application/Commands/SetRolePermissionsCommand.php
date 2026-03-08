<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class SetRolePermissionsCommand
{
    /**
     * @param  list<string>  $permissionNames
     */
    public function __construct(
        public string $roleId,
        public array $permissionNames,
    ) {}
}
