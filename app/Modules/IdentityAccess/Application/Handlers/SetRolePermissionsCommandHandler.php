<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\SetRolePermissionsCommand;
use App\Modules\IdentityAccess\Application\Contracts\PermissionCatalog;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionInvalidationDispatcher;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Modules\IdentityAccess\Application\Data\PermissionDefinitionData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SetRolePermissionsCommandHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RoleRepository $roleRepository,
        private readonly PermissionCatalog $permissionCatalog,
        private readonly UserRoleAssignmentRepository $userRoleAssignmentRepository,
        private readonly PermissionProjectionInvalidationDispatcher $permissionProjectionInvalidationDispatcher,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<PermissionDefinitionData>
     */
    public function handle(SetRolePermissionsCommand $command): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $role = $this->roleRepository->findInTenant($command->roleId, $tenantId);

        if ($role === null) {
            throw new NotFoundHttpException('The requested role does not exist.');
        }

        $permissionNames = $this->normalizedPermissionNames($command->permissionNames);
        $unknownPermissionNames = array_values(array_filter(
            $permissionNames,
            fn (string $permissionName): bool => ! $this->permissionCatalog->exists($permissionName),
        ));

        if ($unknownPermissionNames !== []) {
            throw ValidationException::withMessages([
                'permissions' => [
                    'Unknown permissions: '.implode(', ', $unknownPermissionNames).'.',
                ],
            ]);
        }

        $beforePermissions = $this->roleRepository->listPermissionNames($role->roleId, $tenantId);
        $this->roleRepository->replacePermissions($role->roleId, $tenantId, $permissionNames);

        $assignedUserIds = $this->userRoleAssignmentRepository->assignedUserIdsForRole($role->roleId, $tenantId);
        $this->permissionProjectionInvalidationDispatcher->invalidateMany($assignedUserIds, $tenantId);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'rbac.role_permissions_replaced',
            objectType: 'role',
            objectId: $role->roleId,
            before: ['permissions' => $beforePermissions],
            after: ['permissions' => $permissionNames],
            metadata: ['affected_user_ids' => $assignedUserIds],
        ));

        return $this->permissionCatalog->definitions($permissionNames);
    }

    /**
     * @param  list<string>  $permissionNames
     * @return list<string>
     */
    private function normalizedPermissionNames(array $permissionNames): array
    {
        return array_values(array_unique(array_filter(
            $permissionNames,
            static fn (string $permissionName): bool => $permissionName !== '',
        )));
    }
}
