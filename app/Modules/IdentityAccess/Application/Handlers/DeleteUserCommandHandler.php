<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\DeleteUserCommand;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Application\Services\TenantManagedUserService;

final class DeleteUserCommandHandler
{
    public function __construct(
        private readonly TenantManagedUserService $tenantManagedUserService,
    ) {}

    public function handle(DeleteUserCommand $command): ManagedUserData
    {
        return $this->tenantManagedUserService->delete($command->userId);
    }
}
