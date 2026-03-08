<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates attaches lists filters and shows tenant users without overwriting shared identities', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users1@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantAlpha = (string) Str::uuid();
    $tenantBeta = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantAlpha, ['users.view', 'users.manage']);
    managedUsersGrantPermissions($admin, $tenantBeta, ['users.view', 'users.manage']);
    $token = managedUsersIssueBearerToken($this, 'tenant.admin+users1@openai.com');

    $createResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantAlpha)
        ->postJson('/api/v1/users', [
            'name' => 'Clinic Nurse',
            'email' => 'clinic.nurse+users1@openai.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'status' => 'inactive',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'user_created')
        ->assertJsonPath('data.name', 'Clinic Nurse')
        ->assertJsonPath('data.status', 'inactive');

    $userId = $createResponse->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantBeta)
        ->postJson('/api/v1/users', [
            'name' => 'Tenant Beta Alias',
            'email' => 'clinic.nurse+users1@openai.com',
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'user_created')
        ->assertJsonPath('data.id', $userId)
        ->assertJsonPath('data.name', 'Clinic Nurse')
        ->assertJsonPath('data.status', 'active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantAlpha)
        ->getJson('/api/v1/users?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $userId)
        ->assertJsonPath('data.0.status', 'inactive');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantBeta)
        ->getJson('/api/v1/users?q=clinic')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $userId)
        ->assertJsonPath('data.0.status', 'active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantBeta)
        ->getJson('/api/v1/users/'.$userId)
        ->assertOk()
        ->assertJsonPath('data.name', 'Clinic Nurse')
        ->assertJsonPath('data.email', 'clinic.nurse+users1@openai.com');

    expect(AuditEventRecord::query()->where('action', 'users.created')->where('object_id', $userId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'users.attached')->where('object_id', $userId)->exists())->toBeTrue();
});

it('updates shared identity fields across tenant memberships', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users2@openai.com',
        'password' => 'secret-password',
    ]);
    $target = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'shared.user+users2@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantAlpha = (string) Str::uuid();
    $tenantBeta = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantAlpha, ['users.view', 'users.manage']);
    managedUsersGrantPermissions($admin, $tenantBeta, ['users.view', 'users.manage']);
    managedUsersEnsureMembership($target, $tenantAlpha);
    managedUsersEnsureMembership($target, $tenantBeta);

    $token = managedUsersIssueBearerToken($this, 'tenant.admin+users2@openai.com');
    $targetId = (string) $target->getAuthIdentifier();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantBeta)
        ->patchJson('/api/v1/users/'.$targetId, [
            'name' => 'Updated Shared Name',
            'email' => 'updated.shared.user+users2@openai.com',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'user_updated')
        ->assertJsonPath('data.name', 'Updated Shared Name')
        ->assertJsonPath('data.email', 'updated.shared.user+users2@openai.com');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantAlpha)
        ->getJson('/api/v1/users/'.$targetId)
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Shared Name')
        ->assertJsonPath('data.email', 'updated.shared.user+users2@openai.com');

    expect(AuditEventRecord::query()->where('action', 'users.updated')->where('object_id', $targetId)->exists())->toBeTrue();
});

it('enforces documented tenant user lifecycle transitions', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users3@openai.com',
        'password' => 'secret-password',
    ]);
    $target = User::factory()->create([
        'email' => 'lifecycle.user+users3@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantId, ['users.manage']);
    managedUsersEnsureMembership($target, $tenantId, 'inactive');
    $token = managedUsersIssueBearerToken($this, 'tenant.admin+users3@openai.com');
    $targetId = (string) $target->getAuthIdentifier();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/'.$targetId.':activate')
        ->assertOk()
        ->assertJsonPath('status', 'user_activated')
        ->assertJsonPath('data.status', 'active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/'.$targetId.':activate')
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/'.$targetId.':deactivate')
        ->assertOk()
        ->assertJsonPath('status', 'user_deactivated')
        ->assertJsonPath('data.status', 'inactive');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/'.$targetId.':lock')
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/'.$targetId.':activate')
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/'.$targetId.':lock')
        ->assertOk()
        ->assertJsonPath('status', 'user_locked')
        ->assertJsonPath('data.status', 'locked');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/'.$targetId.':unlock')
        ->assertOk()
        ->assertJsonPath('status', 'user_unlocked')
        ->assertJsonPath('data.status', 'active');

    expect(AuditEventRecord::query()->where('action', 'users.activated')->where('object_id', $targetId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'users.deactivated')->where('object_id', $targetId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'users.locked')->where('object_id', $targetId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'users.unlocked')->where('object_id', $targetId)->exists())->toBeTrue();
});

it('deletes tenant memberships without deleting the shared account or tenant role cleanup', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users4@openai.com',
        'password' => 'secret-password',
    ]);
    $target = User::factory()->create([
        'email' => 'delete.user+users4@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantId, ['users.manage']);
    managedUsersEnsureMembership($target, $tenantId);

    $role = managedUsersCreateRole($tenantId, 'Patient Viewer', ['patients.view']);
    managedUsersAssignRoles($target, $tenantId, [$role->roleId]);
    $token = managedUsersIssueBearerToken($this, 'tenant.admin+users4@openai.com');
    $targetId = (string) $target->getAuthIdentifier();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/users/'.$targetId)
        ->assertOk()
        ->assertJsonPath('status', 'user_deleted')
        ->assertJsonPath('data.id', $targetId);

    expect(managedUsersMembership($targetId, $tenantId))->toBeNull();
    expect(app(UserRoleAssignmentRepository::class)->listRolesForUser($targetId, $tenantId))->toHaveCount(0);
    expect(User::query()->find($targetId))->not->toBeNull();
    expect(AuditEventRecord::query()->where('action', 'users.deleted')->where('object_id', $targetId)->exists())->toBeTrue();
});

it('resets passwords as an admin and revokes all target sessions', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users5@openai.com',
        'password' => 'secret-password',
    ]);
    $target = User::factory()->create([
        'email' => 'reset.user+users5@openai.com',
        'password' => 'old-secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantId, ['users.manage']);
    managedUsersEnsureMembership($target, $tenantId);

    $firstSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'reset.user+users5@openai.com',
        'password' => 'old-secret-password',
    ])->assertOk();

    $secondSession = $this->postJson('/api/v1/auth/login', [
        'email' => 'reset.user+users5@openai.com',
        'password' => 'old-secret-password',
    ])->assertOk();

    $adminToken = managedUsersIssueBearerToken($this, 'tenant.admin+users5@openai.com');
    $targetId = (string) $target->getAuthIdentifier();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/'.$targetId.':reset-password', [
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'user_password_reset')
        ->assertJsonPath('revoked_sessions', 2)
        ->assertJsonPath('data.id', $targetId);

    $this->withToken($firstSession->json('tokens.access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);

    $this->withToken($secondSession->json('tokens.access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'reset.user+users5@openai.com',
        'password' => 'old-secret-password',
    ])->assertStatus(401);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'reset.user+users5@openai.com',
        'password' => 'new-secret-password',
    ])->assertOk();

    expect(AuditEventRecord::query()->where('action', 'users.password_reset_admin')->where('object_id', $targetId)->exists())->toBeTrue();
});

it('bulk imports new attached and existing tenant users with documented counts', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users6@openai.com',
        'password' => 'secret-password',
    ]);
    $attached = User::factory()->create([
        'name' => 'Attached Original',
        'email' => 'attached.original+users6@openai.com',
        'password' => 'secret-password',
    ]);
    $existing = User::factory()->create([
        'name' => 'Existing Member',
        'email' => 'existing.member+users6@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantId, ['users.manage']);
    managedUsersEnsureMembership($existing, $tenantId, 'active');
    $token = managedUsersIssueBearerToken($this, 'tenant.admin+users6@openai.com');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users:bulk-import', [
            'users' => [
                [
                    'name' => 'Bulk New User',
                    'email' => 'bulk.new.user+users6@openai.com',
                    'password' => 'secret-password',
                    'status' => 'inactive',
                ],
                [
                    'name' => 'Attached Alias',
                    'email' => 'attached.original+users6@openai.com',
                    'status' => 'active',
                ],
                [
                    'name' => 'Existing Alias',
                    'email' => 'existing.member+users6@openai.com',
                    'status' => 'inactive',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'users_imported')
        ->assertJsonPath('data.processed_count', 3)
        ->assertJsonPath('data.created_count', 1)
        ->assertJsonPath('data.attached_count', 1)
        ->assertJsonPath('data.existing_count', 1)
        ->assertJsonPath('data.users.0.name', 'Bulk New User')
        ->assertJsonPath('data.users.0.status', 'inactive')
        ->assertJsonPath('data.users.1.name', 'Attached Original')
        ->assertJsonPath('data.users.1.status', 'active')
        ->assertJsonPath('data.users.2.name', 'Existing Member')
        ->assertJsonPath('data.users.2.status', 'active');

    expect(User::query()->where('email', 'attached.original+users6@openai.com')->value('name'))->toBe('Attached Original');
    expect(AuditEventRecord::query()->where('action', 'users.created')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'users.attached')->exists())->toBeTrue();
});

it('applies bulk lifecycle actions and rolls back invalid batches', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users7@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantId, ['users.manage']);
    $token = managedUsersIssueBearerToken($this, 'tenant.admin+users7@openai.com');

    $first = User::factory()->create([
        'email' => 'bulk.first+users7@openai.com',
        'password' => 'secret-password',
    ]);
    $second = User::factory()->create([
        'email' => 'bulk.second+users7@openai.com',
        'password' => 'secret-password',
    ]);
    $mixedActive = User::factory()->create([
        'email' => 'bulk.active+users7@openai.com',
        'password' => 'secret-password',
    ]);
    $mixedInactive = User::factory()->create([
        'email' => 'bulk.inactive+users7@openai.com',
        'password' => 'secret-password',
    ]);

    managedUsersEnsureMembership($first, $tenantId, 'inactive');
    managedUsersEnsureMembership($second, $tenantId, 'inactive');
    managedUsersEnsureMembership($mixedActive, $tenantId, 'active');
    managedUsersEnsureMembership($mixedInactive, $tenantId, 'inactive');

    $firstId = (string) $first->getAuthIdentifier();
    $secondId = (string) $second->getAuthIdentifier();
    $mixedActiveId = (string) $mixedActive->getAuthIdentifier();
    $mixedInactiveId = (string) $mixedInactive->getAuthIdentifier();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/bulk', [
            'action' => 'activate',
            'user_ids' => [$firstId, $secondId],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'users_bulk_updated')
        ->assertJsonPath('data.action', 'activate')
        ->assertJsonPath('data.affected_count', 2);

    expect(managedUsersMembership($firstId, $tenantId)?->status)->toBe('active');
    expect(managedUsersMembership($secondId, $tenantId)?->status)->toBe('active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/bulk', [
            'action' => 'lock',
            'user_ids' => [$firstId, $secondId],
        ])
        ->assertOk()
        ->assertJsonPath('data.action', 'lock');

    expect(managedUsersMembership($firstId, $tenantId)?->status)->toBe('locked');
    expect(managedUsersMembership($secondId, $tenantId)?->status)->toBe('locked');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/bulk', [
            'action' => 'unlock',
            'user_ids' => [$firstId, $secondId],
        ])
        ->assertOk()
        ->assertJsonPath('data.action', 'unlock');

    expect(managedUsersMembership($firstId, $tenantId)?->status)->toBe('active');
    expect(managedUsersMembership($secondId, $tenantId)?->status)->toBe('active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/bulk', [
            'action' => 'deactivate',
            'user_ids' => [$firstId, $secondId],
        ])
        ->assertOk()
        ->assertJsonPath('data.action', 'deactivate');

    expect(managedUsersMembership($firstId, $tenantId)?->status)->toBe('inactive');
    expect(managedUsersMembership($secondId, $tenantId)?->status)->toBe('inactive');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/bulk', [
            'action' => 'lock',
            'user_ids' => [$mixedActiveId, $mixedInactiveId],
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    expect(managedUsersMembership($mixedActiveId, $tenantId)?->status)->toBe('active');
    expect(managedUsersMembership($mixedInactiveId, $tenantId)?->status)->toBe('inactive');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/users/bulk', [
            'action' => 'delete',
            'user_ids' => [$firstId, $secondId],
        ])
        ->assertOk()
        ->assertJsonPath('data.action', 'delete')
        ->assertJsonPath('data.affected_count', 2);

    expect(managedUsersMembership($firstId, $tenantId))->toBeNull();
    expect(managedUsersMembership($secondId, $tenantId))->toBeNull();
});

it('returns empty effective permissions when a tenant membership is inactive or locked', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users8@openai.com',
        'password' => 'secret-password',
    ]);
    $staff = User::factory()->create([
        'email' => 'staff.permissions+users8@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantId, ['rbac.view', 'rbac.manage']);
    managedUsersEnsureMembership($staff, $tenantId, 'active');
    $role = managedUsersCreateRole($tenantId, 'Patient Viewer', ['patients.view']);
    managedUsersAssignRoles($staff, $tenantId, [$role->roleId]);
    $token = managedUsersIssueBearerToken($this, 'tenant.admin+users8@openai.com');
    $staffId = (string) $staff->getAuthIdentifier();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/users/'.$staffId.'/permissions')
        ->assertOk()
        ->assertJsonFragment(['name' => 'patients.view']);

    managedUsersEnsureMembership($staff, $tenantId, 'inactive');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/users/'.$staffId.'/permissions')
        ->assertOk()
        ->assertJsonCount(0, 'data.permissions')
        ->assertJsonPath('data.roles.0.id', $role->roleId);

    managedUsersEnsureMembership($staff, $tenantId, 'locked');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/users/'.$staffId.'/permissions')
        ->assertOk()
        ->assertJsonCount(0, 'data.permissions')
        ->assertJsonPath('data.roles.0.id', $role->roleId);
});

it('returns 404 for RBAC user role routes when the target user lacks tenant membership', function (): void {
    $admin = User::factory()->create([
        'email' => 'tenant.admin+users9@openai.com',
        'password' => 'secret-password',
    ]);
    $target = User::factory()->create([
        'email' => 'outsider.user+users9@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    managedUsersGrantPermissions($admin, $tenantId, ['rbac.view', 'rbac.manage']);
    $token = managedUsersIssueBearerToken($this, 'tenant.admin+users9@openai.com');
    $targetId = (string) $target->getAuthIdentifier();
    $role = managedUsersCreateRole($tenantId, 'Scheduler', ['appointments.view']);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/users/'.$targetId.'/roles')
        ->assertStatus(404);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/users/'.$targetId.'/roles', [
            'role_ids' => [$role->roleId],
        ])
        ->assertStatus(404);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/users/'.$targetId.'/permissions')
        ->assertStatus(404);
});

function managedUsersIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return $testCase->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('tokens.access_token');
}

/**
 * @return \App\Modules\IdentityAccess\Application\Data\RoleData
 */
function managedUsersCreateRole(string $tenantId, string $name, array $permissions = [])
{
    /** @var RoleRepository $roleRepository */
    $roleRepository = app(RoleRepository::class);
    $role = $roleRepository->create($tenantId, $name, null);
    $roleRepository->replacePermissions($role->roleId, $tenantId, $permissions);

    return $role;
}

function managedUsersAssignRoles(User $user, string $tenantId, array $roleIds): void
{
    managedUsersEnsureMembership($user, $tenantId);

    /** @var UserRoleAssignmentRepository $userRoleAssignmentRepository */
    $userRoleAssignmentRepository = app(UserRoleAssignmentRepository::class);
    $userRoleAssignmentRepository->replaceRolesForUser((string) $user->getAuthIdentifier(), $tenantId, $roleIds);
}

function managedUsersEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
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

/**
 * @return \App\Modules\IdentityAccess\Application\Data\ManagedUserData|null
 */
function managedUsersMembership(string $userId, string $tenantId)
{
    /** @var ManagedUserRepository $managedUserRepository */
    $managedUserRepository = app(ManagedUserRepository::class);

    return $managedUserRepository->findInTenant($userId, $tenantId);
}

function managedUsersGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    $role = managedUsersCreateRole(
        $tenantId,
        'bootstrap-'.Str::lower(Str::random(8)),
        $permissions,
    );

    managedUsersAssignRoles($user, $tenantId, [$role->roleId]);
}
