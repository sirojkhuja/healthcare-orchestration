<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\SetUserRolesCommand;
use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionInvalidationDispatcher;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Modules\IdentityAccess\Application\Data\RoleData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SetUserRolesCommandHandler
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IdentityUserProvider $identityUserProvider,
        private readonly RoleRepository $roleRepository,
        private readonly UserRoleAssignmentRepository $userRoleAssignmentRepository,
        private readonly PermissionProjectionInvalidationDispatcher $permissionProjectionInvalidationDispatcher,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<RoleData>
     */
    public function handle(SetUserRolesCommand $command): array
    {
        if ($this->identityUserProvider->findById($command->userId) === null) {
            throw new NotFoundHttpException('The requested user does not exist.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $roleIds = $this->normalizedRoleIds($command->roleIds);
        $resolvedRoles = $this->resolvedRoles($roleIds, $tenantId);
        $beforeRoles = $this->userRoleAssignmentRepository->listRolesForUser($command->userId, $tenantId);

        $this->userRoleAssignmentRepository->replaceRolesForUser($command->userId, $tenantId, $roleIds);
        $this->permissionProjectionInvalidationDispatcher->invalidate($command->userId, $tenantId);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'rbac.user_roles_replaced',
            objectType: 'user',
            objectId: $command->userId,
            before: ['roles' => $this->rolePayload($beforeRoles)],
            after: ['roles' => $this->rolePayload($resolvedRoles)],
        ));

        return $resolvedRoles;
    }

    /**
     * @param  list<string>  $roleIds
     * @return list<string>
     */
    private function normalizedRoleIds(array $roleIds): array
    {
        return array_values(array_unique(array_filter(
            $roleIds,
            static fn (string $roleId): bool => $roleId !== '',
        )));
    }

    /**
     * @param  list<string>  $roleIds
     * @return list<RoleData>
     */
    private function resolvedRoles(array $roleIds, string $tenantId): array
    {
        $roles = [];
        $missingRoleIds = [];

        foreach ($roleIds as $roleId) {
            $role = $this->roleRepository->findInTenant($roleId, $tenantId);

            if ($role === null) {
                $missingRoleIds[] = $roleId;

                continue;
            }

            $roles[] = $role;
        }

        if ($missingRoleIds !== []) {
            throw ValidationException::withMessages([
                'role_ids' => [
                    'Unknown roles for the active tenant: '.implode(', ', $missingRoleIds).'.',
                ],
            ]);
        }

        usort($roles, static fn (RoleData $left, RoleData $right): int => strcmp($left->name, $right->name));

        return $roles;
    }

    /**
     * @param  list<RoleData>  $roles
     * @return list<array{id: string, name: string}>
     */
    private function rolePayload(array $roles): array
    {
        return array_map(
            static fn (RoleData $role): array => [
                'id' => $role->roleId,
                'name' => $role->name,
            ],
            $roles,
        );
    }
}
