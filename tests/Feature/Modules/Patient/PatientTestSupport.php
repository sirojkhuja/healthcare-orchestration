<?php

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use Illuminate\Support\Str;

function patientCreateRole(string $tenantId, string $name, array $permissions = [])
{
    /** @var RoleRepository $roleRepository */
    $roleRepository = app(RoleRepository::class);
    $role = $roleRepository->create($tenantId, $name, null);
    $roleRepository->replacePermissions($role->roleId, $tenantId, $permissions);

    return $role;
}

function patientCreateTenant($testCase, string $token, string $name)
{
    $testCase->flushHeaders();

    return $testCase->withToken($token)->postJson('/api/v1/tenants', [
        'name' => $name,
    ])->assertCreated();
}

function patientEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
{
    /** @var ManagedUserRepository $managedUserRepository */
    $managedUserRepository = app(ManagedUserRepository::class);
    $userId = (string) $user->getAuthIdentifier();
    $membership = $managedUserRepository->findInTenant($userId, $tenantId);

    if ($membership === null) {
        $managedUserRepository->attachToTenant($userId, $tenantId, $status);

        return;
    }

    if ($membership->status !== $status) {
        $managedUserRepository->updateStatusInTenant($userId, $tenantId, $status);
    }
}

function patientGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    $role = patientCreateRole(
        $tenantId,
        'patient-bootstrap-'.Str::lower(Str::random(8)),
        $permissions,
    );

    patientAssignRoles($user, $tenantId, [$role->roleId]);
}

function patientAssignRoles(User $user, string $tenantId, array $roleIds): void
{
    patientEnsureMembership($user, $tenantId);

    /** @var UserRoleAssignmentRepository $userRoleAssignmentRepository */
    $userRoleAssignmentRepository = app(UserRoleAssignmentRepository::class);
    $userRoleAssignmentRepository->replaceRolesForUser((string) $user->getAuthIdentifier(), $tenantId, $roleIds);
}

function patientIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return $testCase->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('tokens.access_token');
}
