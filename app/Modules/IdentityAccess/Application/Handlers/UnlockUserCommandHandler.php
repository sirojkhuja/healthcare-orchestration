<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\UnlockUserCommand;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Application\Services\TenantManagedUserService;

final class UnlockUserCommandHandler
{
    public function __construct(
        private readonly TenantManagedUserService $tenantManagedUserService,
    ) {}

    public function handle(UnlockUserCommand $command): ManagedUserData
    {
        return $this->tenantManagedUserService->unlock($command->userId);
    }
}
