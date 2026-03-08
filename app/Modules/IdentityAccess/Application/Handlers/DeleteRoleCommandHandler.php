<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\DeleteRoleCommand;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteRoleCommandHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RoleRepository $roleRepository,
        private readonly UserRoleAssignmentRepository $userRoleAssignmentRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(DeleteRoleCommand $command): void
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $role = $this->roleRepository->findInTenant($command->roleId, $tenantId);

        if ($role === null) {
            throw new NotFoundHttpException('The requested role does not exist.');
        }

        $assignedUserIds = $this->userRoleAssignmentRepository->assignedUserIdsForRole($role->roleId, $tenantId);

        if ($assignedUserIds !== []) {
            throw new ConflictHttpException('Assigned roles cannot be deleted until all user assignments are removed.');
        }

        if (! $this->roleRepository->deleteInTenant($role->roleId, $tenantId)) {
            throw new NotFoundHttpException('The requested role does not exist.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'rbac.role_deleted',
            objectType: 'role',
            objectId: $role->roleId,
            before: $role->toArray(),
        ));
    }
}
