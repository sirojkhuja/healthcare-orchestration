<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns and updates the current user profile without tenant context', function (): void {
    $user = User::factory()->create([
        'email' => 'profile.self+1@openai.com',
        'password' => 'secret-password',
        'phone' => null,
        'job_title' => null,
        'locale' => null,
        'timezone' => null,
    ]);

    $token = profileIssueBearerToken($this, 'profile.self+1@openai.com');
    $userId = (string) $user->getAuthIdentifier();

    $this->withToken($token)
        ->getJson('/api/v1/profiles/me')
        ->assertOk()
        ->assertJsonPath('data.id', $userId)
        ->assertJsonPath('data.email', 'profile.self+1@openai.com')
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.avatar', null);

    $this->withToken($token)
        ->patchJson('/api/v1/profiles/me', [
            'name' => 'Profile Self Updated',
            'phone' => '+998901234567',
            'job_title' => 'Front Desk Lead',
            'locale' => 'uz',
            'timezone' => 'Asia/Tashkent',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'profile_updated')
        ->assertJsonPath('data.name', 'Profile Self Updated')
        ->assertJsonPath('data.phone', '+998901234567')
        ->assertJsonPath('data.job_title', 'Front Desk Lead')
        ->assertJsonPath('data.locale', 'uz')
        ->assertJsonPath('data.timezone', 'Asia/Tashkent');

    expect(AuditEventRecord::query()->where('action', 'profiles.updated')->where('object_id', $userId)->exists())->toBeTrue();
});

it('uploads and replaces the current user avatar on the attachments disk', function (): void {
    Storage::fake('attachments');

    $user = User::factory()->create([
        'email' => 'profile.self+2@openai.com',
        'password' => 'secret-password',
    ]);

    $token = profileIssueBearerToken($this, 'profile.self+2@openai.com');
    $userId = (string) $user->getAuthIdentifier();

    $firstResponse = $this->withToken($token)
        ->post('/api/v1/profiles/me/avatar', [
            'avatar' => profileAvatarUpload('avatar-one.png'),
        ])
        ->assertOk()
        ->assertJsonPath('status', 'avatar_uploaded')
        ->assertJsonPath('data.avatar.file_name', 'avatar-one.png');

    $firstPath = (string) User::query()->findOrFail($userId)->getAttribute('avatar_path');
    Storage::disk('attachments')->assertExists($firstPath);
    expect($firstResponse->json('data.avatar.mime_type'))->toBe('image/png');

    $this->withToken($token)
        ->post('/api/v1/profiles/me/avatar', [
            'avatar' => profileAvatarUpload('avatar-two.png'),
        ])
        ->assertOk()
        ->assertJsonPath('status', 'avatar_uploaded')
        ->assertJsonPath('data.avatar.file_name', 'avatar-two.png')
        ->assertJsonPath('data.avatar.mime_type', 'image/png');

    $secondPath = (string) User::query()->findOrFail($userId)->getAttribute('avatar_path');

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('attachments')->assertExists($secondPath);
    Storage::disk('attachments')->assertMissing($firstPath);
    expect(AuditEventRecord::query()->where('action', 'profiles.avatar_uploaded')->where('object_id', $userId)->count())->toBe(2);
});

it('allows tenant admins to view and update member profiles while rejecting users outside the tenant', function (): void {
    $admin = User::factory()->create([
        'email' => 'profile.admin+1@openai.com',
        'password' => 'secret-password',
    ]);
    $target = User::factory()->create([
        'email' => 'profile.target+1@openai.com',
        'password' => 'secret-password',
        'phone' => '+998900000000',
    ]);
    $outsider = User::factory()->create([
        'email' => 'profile.outsider+1@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    profileGrantPermissions($admin, $tenantId, ['profiles.view', 'profiles.manage']);
    profileEnsureMembership($target, $tenantId);
    $token = profileIssueBearerToken($this, 'profile.admin+1@openai.com');
    $targetId = (string) $target->getAuthIdentifier();
    $outsiderId = (string) $outsider->getAuthIdentifier();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/profiles/'.$targetId)
        ->assertOk()
        ->assertJsonPath('data.id', $targetId)
        ->assertJsonPath('data.phone', '+998900000000');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/profiles/'.$targetId, [
            'phone' => '+998901111111',
            'job_title' => 'Care Coordinator',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'profile_updated')
        ->assertJsonPath('data.phone', '+998901111111')
        ->assertJsonPath('data.job_title', 'Care Coordinator');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/profiles/'.$outsiderId)
        ->assertStatus(404);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/profiles/'.$outsiderId, [
            'phone' => '+998902222222',
        ])
        ->assertStatus(404);

    expect(AuditEventRecord::query()->where('action', 'profiles.updated')->where('object_id', $targetId)->exists())->toBeTrue();
});

it('enforces profile permissions on tenant-admin profile routes', function (): void {
    $viewer = User::factory()->create([
        'email' => 'profile.viewer+1@openai.com',
        'password' => 'secret-password',
    ]);
    $outsider = User::factory()->create([
        'email' => 'profile.outsider+2@openai.com',
        'password' => 'secret-password',
    ]);
    $target = User::factory()->create([
        'email' => 'profile.target+2@openai.com',
        'password' => 'secret-password',
    ]);
    $tenantId = (string) Str::uuid();

    profileGrantPermissions($viewer, $tenantId, ['profiles.view']);
    profileEnsureMembership($target, $tenantId);

    $viewerToken = profileIssueBearerToken($this, 'profile.viewer+1@openai.com');
    $outsiderToken = profileIssueBearerToken($this, 'profile.outsider+2@openai.com');
    $targetId = (string) $target->getAuthIdentifier();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/profiles/'.$targetId)
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/profiles/'.$targetId, [
            'job_title' => 'Blocked Update',
        ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($outsiderToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/profiles/'.$targetId)
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

function profileIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return $testCase->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('tokens.access_token');
}

function profileAvatarUpload(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0p8AAAAASUVORK5CYII=', true) ?: '',
    );
}

function profileEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
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

function profileAssignRoles(User $user, string $tenantId, array $roleIds): void
{
    profileEnsureMembership($user, $tenantId);

    /** @var UserRoleAssignmentRepository $userRoleAssignmentRepository */
    $userRoleAssignmentRepository = app(UserRoleAssignmentRepository::class);
    $userRoleAssignmentRepository->replaceRolesForUser((string) $user->getAuthIdentifier(), $tenantId, $roleIds);
}

function profileGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    /** @var RoleRepository $roleRepository */
    $roleRepository = app(RoleRepository::class);
    $role = $roleRepository->create($tenantId, 'profile-'.Str::lower(Str::random(8)), null);
    $roleRepository->replacePermissions($role->roleId, $tenantId, $permissions);

    profileAssignRoles($user, $tenantId, [$role->roleId]);
}
