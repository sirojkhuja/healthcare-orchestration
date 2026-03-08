<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class PermissionProjection
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $userId,
        public ?string $tenantId,
        public array $permissions,
    ) {}

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
