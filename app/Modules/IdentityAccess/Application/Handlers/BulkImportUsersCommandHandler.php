<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\BulkImportUsersCommand;
use App\Modules\IdentityAccess\Application\Data\BulkImportUsersResultData;
use App\Modules\IdentityAccess\Application\Services\TenantManagedUserService;

final class BulkImportUsersCommandHandler
{
    public function __construct(
        private readonly TenantManagedUserService $tenantManagedUserService,
    ) {}

    public function handle(BulkImportUsersCommand $command): BulkImportUsersResultData
    {
        return $this->tenantManagedUserService->bulkImport($command->users);
    }
}
