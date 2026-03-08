<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\UpdateUserCommand;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Application\Services\TenantManagedUserService;

final class UpdateUserCommandHandler
{
    public function __construct(
        private readonly TenantManagedUserService $tenantManagedUserService,
    ) {}

    public function handle(UpdateUserCommand $command): ManagedUserData
    {
        return $this->tenantManagedUserService->update($command->userId, $command->name, $command->email);
    }
}
