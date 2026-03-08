<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\DeactivateUserCommand;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Application\Services\TenantManagedUserService;

final class DeactivateUserCommandHandler
{
    public function __construct(
        private readonly TenantManagedUserService $tenantManagedUserService,
    ) {}

    public function handle(DeactivateUserCommand $command): ManagedUserData
    {
        return $this->tenantManagedUserService->deactivate($command->userId);
    }
}
