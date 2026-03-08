<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\AdminResetPasswordCommand;
use App\Modules\IdentityAccess\Application\Services\TenantManagedUserService;

final class AdminResetPasswordCommandHandler
{
    public function __construct(
        private readonly TenantManagedUserService $tenantManagedUserService,
    ) {}

    /**
     * @return array{user: \App\Modules\IdentityAccess\Application\Data\ManagedUserData, revoked_sessions: int}
     */
    public function handle(AdminResetPasswordCommand $command): array
    {
        /** @var array{user: \App\Modules\IdentityAccess\Application\Data\ManagedUserData, revoked_sessions: int} $result */
        $result = $this->tenantManagedUserService->resetPassword($command->userId, $command->password);

        return $result;
    }
}
