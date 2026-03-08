<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! Route::has('tests.identity-access.rbac.patients')) {
        Route::middleware(['api', 'tenant.require', 'auth:api', 'permission:patients.view'])
            ->get('/api/v1/_tests/identity-access/rbac/patients', fn () => response()->json(['status' => 'ok']))
            ->name('tests.identity-access.rbac.patients');
    }
});

it('lists permission catalogs and groups for tenant-scoped viewers', function (): void {
    $viewer = User::factory()->create([
        'email' => 'viewer@example.test',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    grantPermissions($viewer, $tenantId, ['rbac.view']);
    $token = issueBearerToken($this, 'viewer@example.test');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'admin.manage')
        ->assertJsonPath('data.1.name', 'admin.view');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/permissions/groups')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'rbac')
        ->assertJsonPath('data.0.permissions.0.name', 'rbac.view');
});

it('creates updates lists and deletes tenant-scoped roles', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.test',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    grantPermissions($admin, $tenantId, ['rbac.view', 'rbac.manage']);
    $token = issueBearerToken($this, 'admin@example.test');

    $createResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/roles', [
            'name' => 'Care Coordinator',
            'description' => 'Coordinates care pathways.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'role_created')
        ->assertJsonPath('data.name', 'Care Coordinator');

    $roleId = $createResponse->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $roleId,
            'name' => 'Care Coordinator',
        ]);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/roles/'.$roleId)
        ->assertOk()
        ->assertJsonPath('data.description', 'Coordinates care pathways.');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/roles/'.$roleId, [
            'name' => 'Care Navigator',
            'description' => null,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'role_updated')
        ->assertJsonPath('data.name', 'Care Navigator')
        ->assertJsonPath('data.description', null);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/roles/'.$roleId)
        ->assertOk()
        ->assertJsonPath('status', 'role_deleted');

    expect(AuditEventRecord::query()->where('action', 'rbac.role_created')->where('object_id', $roleId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'rbac.role_updated')->where('object_id', $roleId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'rbac.role_deleted')->where('object_id', $roleId)->exists())->toBeTrue();
});

it('replaces role permissions and blocks deleting assigned roles', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin-roles@example.test',
        'password' => 'secret-password',
    ]);
    $staff = User::factory()->create();
    $tenantId = (string) Str::uuid();

    grantPermissions($admin, $tenantId, ['rbac.view', 'rbac.manage']);
    ensureTenantMembership($staff, $tenantId);
    $token = issueBearerToken($this, 'admin-roles@example.test');

    $roleId = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/roles', [
            'name' => 'Scheduler',
            'description' => 'Coordinates booking operations.',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/roles/'.$roleId.'/permissions', [
            'permissions' => ['appointments.view', 'appointments.manage'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'role_permissions_updated')
        ->assertJsonCount(2, 'data');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/roles/'.$roleId.'/permissions')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'appointments.manage')
        ->assertJsonPath('data.1.name', 'appointments.view');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/users/'.(string) $staff->getAuthIdentifier().'/roles', [
            'role_ids' => [$roleId],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'user_roles_updated')
        ->assertJsonPath('data.0.id', $roleId);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/roles/'.$roleId)
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/users/'.(string) $staff->getAuthIdentifier().'/roles', [
            'role_ids' => [],
        ])
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/roles/'.$roleId)
        ->assertOk()
        ->assertJsonPath('status', 'role_deleted');
});

it('returns effective user roles and permissions inside the active tenant', function (): void {
    $admin = User::factory()->create([
        'email' => 'permissions-admin@example.test',
        'password' => 'secret-password',
    ]);
    $staff = User::factory()->create();
    $tenantId = (string) Str::uuid();

    grantPermissions($admin, $tenantId, ['rbac.view', 'rbac.manage']);
    ensureTenantMembership($staff, $tenantId);
    $token = issueBearerToken($this, 'permissions-admin@example.test');

    $frontDeskRole = createTenantRole($tenantId, 'Front Desk', ['patients.view']);
    $BillingRole = createTenantRole($tenantId, 'Billing', ['billing.view']);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/users/'.(string) $staff->getAuthIdentifier().'/roles', [
            'role_ids' => [$BillingRole->roleId, $frontDeskRole->roleId],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/users/'.(string) $staff->getAuthIdentifier().'/roles')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Billing'])
        ->assertJsonFragment(['name' => 'Front Desk']);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/users/'.(string) $staff->getAuthIdentifier().'/permissions')
        ->assertOk()
        ->assertJsonPath('data.user_id', (string) $staff->getAuthIdentifier())
        ->assertJsonPath('data.tenant_id', Str::lower($tenantId))
        ->assertJsonFragment(['name' => 'billing.view'])
        ->assertJsonFragment(['name' => 'patients.view']);
});

it('returns tenant-scoped rbac audit events newest first', function (): void {
    $admin = User::factory()->create([
        'email' => 'audit-admin@example.test',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    grantPermissions($admin, $tenantId, ['rbac.view', 'rbac.manage']);
    $token = issueBearerToken($this, 'audit-admin@example.test');

    $roleId = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/roles', [
            'name' => 'Audited Role',
            'description' => 'Tracks audit ordering.',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/roles/'.$roleId, [
            'name' => 'Audited Role Updated',
        ])
        ->assertOk();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/roles/'.$roleId.'/permissions', [
            'permissions' => ['patients.view'],
        ])
        ->assertOk();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/rbac/audit?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.action', 'rbac.role_permissions_replaced')
        ->assertJsonPath('data.1.action', 'rbac.role_updated');
});

it('enforces rbac view and manage middleware contracts', function (): void {
    $viewer = User::factory()->create([
        'email' => 'rbac-viewer@example.test',
        'password' => 'secret-password',
    ]);
    $outsider = User::factory()->create([
        'email' => 'rbac-outsider@example.test',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    grantPermissions($viewer, $tenantId, ['rbac.view']);
    $viewerToken = issueBearerToken($this, 'rbac-viewer@example.test');
    $outsiderToken = issueBearerToken($this, 'rbac-outsider@example.test');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/roles')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/roles', [
            'name' => 'Blocked Mutation',
        ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($outsiderToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/roles')
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('invalidates cached permissions after role permission and user role replacement', function (): void {
    $admin = User::factory()->create([
        'email' => 'cache-admin@example.test',
        'password' => 'secret-password',
    ]);
    $staff = User::factory()->create([
        'email' => 'cache-staff@example.test',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    grantPermissions($admin, $tenantId, ['rbac.view', 'rbac.manage']);
    $staffRole = createTenantRole($tenantId, 'Patient Viewer', ['patients.view']);
    assignTenantRoles($staff, $tenantId, [$staffRole->roleId]);

    $adminToken = issueBearerToken($this, 'cache-admin@example.test');
    $staffToken = issueBearerToken($this, 'cache-staff@example.test');

    $this->withToken($staffToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/_tests/identity-access/rbac/patients')
        ->assertOk();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/roles/'.$staffRole->roleId.'/permissions', [
            'permissions' => [],
        ])
        ->assertOk();

    $this->withToken($staffToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/_tests/identity-access/rbac/patients')
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    $replacementRole = createTenantRole($tenantId, 'Patient Viewer Replacement', ['patients.view']);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/users/'.(string) $staff->getAuthIdentifier().'/roles', [
            'role_ids' => [$replacementRole->roleId],
        ])
        ->assertOk();

    $this->withToken($staffToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/_tests/identity-access/rbac/patients')
        ->assertOk();
});

function issueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return $testCase->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('tokens.access_token');
}

function createTenantRole(string $tenantId, string $name, array $permissions = [])
{
    /** @var RoleRepository $roleRepository */
    $roleRepository = app(RoleRepository::class);
    $role = $roleRepository->create($tenantId, $name, null);
    $roleRepository->replacePermissions($role->roleId, $tenantId, $permissions);

    return $role;
}

function assignTenantRoles(User $user, string $tenantId, array $roleIds): void
{
    ensureTenantMembership($user, $tenantId);

    /** @var UserRoleAssignmentRepository $userRoleAssignmentRepository */
    $userRoleAssignmentRepository = app(UserRoleAssignmentRepository::class);
    $userRoleAssignmentRepository->replaceRolesForUser((string) $user->getAuthIdentifier(), $tenantId, $roleIds);
}

function ensureTenantMembership(User $user, string $tenantId, string $status = 'active'): void
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

function grantPermissions(User $user, string $tenantId, array $permissions): void
{
    $role = createTenantRole(
        $tenantId,
        'bootstrap-'.Str::lower(Str::random(8)),
        $permissions,
    );

    assignTenantRoles($user, $tenantId, [$role->roleId]);
}
