<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\BulkUpdateUsersCommand;
use App\Modules\IdentityAccess\Application\Data\BulkUserActionResultData;
use App\Modules\IdentityAccess\Application\Services\TenantManagedUserService;

final class BulkUpdateUsersCommandHandler
{
    public function __construct(
        private readonly TenantManagedUserService $tenantManagedUserService,
    ) {}

    public function handle(BulkUpdateUsersCommand $command): BulkUserActionResultData
    {
        return $this->tenantManagedUserService->bulkAction($command->action, $command->userIds);
    }
}
