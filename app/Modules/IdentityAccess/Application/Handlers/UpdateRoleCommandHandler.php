<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\UpdateRoleCommand;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Data\RoleData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateRoleCommandHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RoleRepository $roleRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(UpdateRoleCommand $command): RoleData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $existingRole = $this->roleRepository->findInTenant($command->roleId, $tenantId);

        if ($existingRole === null) {
            throw new NotFoundHttpException('The requested role does not exist.');
        }

        $name = $command->name ?? $existingRole->name;
        $description = $command->descriptionProvided ? $command->description : $existingRole->description;

        if ($this->roleRepository->nameExists($tenantId, $name, $existingRole->roleId)) {
            throw new ConflictHttpException('A role with this name already exists in the active tenant.');
        }

        $updatedRole = $this->roleRepository->updateInTenant(
            $existingRole->roleId,
            $tenantId,
            $name,
            $description,
        );

        if ($updatedRole === null) {
            throw new NotFoundHttpException('The requested role does not exist.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'rbac.role_updated',
            objectType: 'role',
            objectId: $updatedRole->roleId,
            before: $existingRole->toArray(),
            after: $updatedRole->toArray(),
        ));

        return $updatedRole;
    }
}
