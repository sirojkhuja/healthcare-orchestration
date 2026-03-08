<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\CreateRoleCommand;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Data\RoleData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CreateRoleCommandHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RoleRepository $roleRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(CreateRoleCommand $command): RoleData
    {
        $tenantId = $this->tenantContext->requireTenantId();

        if ($this->roleRepository->nameExists($tenantId, $command->name)) {
            throw new ConflictHttpException('A role with this name already exists in the active tenant.');
        }

        $role = $this->roleRepository->create($tenantId, $command->name, $command->description);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'rbac.role_created',
            objectType: 'role',
            objectId: $role->roleId,
            after: $role->toArray(),
        ));

        return $role;
    }
}
