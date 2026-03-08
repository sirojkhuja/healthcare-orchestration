<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\CreateUserCommand;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Application\Services\TenantManagedUserService;

final class CreateUserCommandHandler
{
    public function __construct(
        private readonly TenantManagedUserService $tenantManagedUserService,
    ) {}

    public function handle(CreateUserCommand $command): ManagedUserData
    {
        return $this->tenantManagedUserService->create(
            $command->name,
            $command->email,
            $command->password,
            $command->status ?? 'active',
        );
    }
}
