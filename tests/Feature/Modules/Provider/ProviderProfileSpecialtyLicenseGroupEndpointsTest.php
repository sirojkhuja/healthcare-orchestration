<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/ProviderTestSupport.php';

uses(RefreshDatabase::class);

it('manages provider profile specialties licenses and groups', function (): void {
    User::factory()->create([
        'email' => 'provider.extensions.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'provider.extensions.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'provider.extensions.admin@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'provider.extensions.viewer@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Provider Extensions Alpha')->json('data.id');
    $clinicId = providerCreateClinic($this, $adminToken, $tenantId, 'alpha-provider-ext', 'Provider Extensions Clinic')->json('data.id');
    $departmentId = providerCreateDepartment($this, $adminToken, $tenantId, $clinicId, 'cardio', 'Cardiology')->json('data.id');
    $roomId = providerCreateRoom($this, $adminToken, $tenantId, $clinicId, $departmentId, 'room-a1', 'Room A1')->json('data.id');

    providerGrantPermissions($viewer, $tenantId, ['providers.view']);

    $providerId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'provider_type' => 'doctor',
            'clinic_id' => $clinicId,
        ])
        ->assertCreated()
        ->json('data.id');

    $secondProviderId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Bekzod',
            'last_name' => 'Nazarov',
            'provider_type' => 'nurse',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/providers/'.$providerId.'/profile', [
            'professional_title' => 'Senior Cardiologist',
            'bio' => 'Focuses on preventive cardiac care.',
            'years_of_experience' => 12,
            'department_id' => $departmentId,
            'room_id' => $roomId,
            'is_accepting_new_patients' => false,
            'languages' => ['Uzbek', 'English', 'English'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'provider_profile_updated')
        ->assertJsonPath('data.professional_title', 'Senior Cardiologist')
        ->assertJsonPath('data.years_of_experience', 12)
        ->assertJsonPath('data.languages.0', 'English')
        ->assertJsonPath('data.languages.1', 'Uzbek')
        ->assertJsonPath('data.department_id', $departmentId)
        ->assertJsonPath('data.room_id', $roomId)
        ->assertJsonPath('data.is_accepting_new_patients', false);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/profile')
        ->assertOk()
        ->assertJsonPath('data.department.id', $departmentId)
        ->assertJsonPath('data.room.id', $roomId);

    $cardiologyId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/specialties', [
            'name' => 'Cardiology',
            'description' => 'Heart and vascular medicine',
        ])
        ->assertCreated()
        ->json('data.id');

    $pediatricsId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/specialties', [
            'name' => 'Pediatrics',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/providers/'.$providerId.'/specialties', [
            'specialties' => [
                ['specialty_id' => $cardiologyId, 'is_primary' => true],
                ['specialty_id' => $pediatricsId],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'provider_specialties_updated')
        ->assertJsonPath('data.0.specialty_id', $cardiologyId)
        ->assertJsonPath('data.0.is_primary', true);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/specialties')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/specialties')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.specialty_id', $cardiologyId);

    $activeLicenseId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers/'.$providerId.'/licenses', [
            'license_type' => 'Medical License',
            'license_number' => 'ML-001',
            'issuing_authority' => 'Tashkent Medical Board',
            'issued_on' => '2020-01-10',
            'expires_on' => '2030-01-10',
        ])
        ->assertCreated()
        ->json('data.id');

    $expiredLicenseId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers/'.$providerId.'/licenses', [
            'license_type' => 'Research Permit',
            'license_number' => 'RP-002',
            'issuing_authority' => 'Health Authority',
            'expires_on' => '2024-12-31',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/licenses')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $activeLicenseId)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.1.id', $expiredLicenseId)
        ->assertJsonPath('data.1.status', 'expired');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/providers/'.$providerId.'/licenses/'.$expiredLicenseId)
        ->assertOk()
        ->assertJsonPath('status', 'provider_license_removed')
        ->assertJsonPath('data.id', $expiredLicenseId);

    $groupId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/provider-groups', [
            'name' => 'Cardiac Team',
            'description' => 'Core cardiac care group',
            'clinic_id' => $clinicId,
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/provider-groups/'.$groupId.'/members', [
            'provider_ids' => [$providerId, $secondProviderId],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'provider_group_members_updated')
        ->assertJsonPath('data.member_count', 2);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/provider-groups')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $groupId)
        ->assertJsonPath('data.0.member_count', 2);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/providers/'.$providerId, [
            'clinic_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'provider_updated');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/profile')
        ->assertOk()
        ->assertJsonPath('data.department_id', null)
        ->assertJsonPath('data.room_id', null)
        ->assertJsonPath('data.department', null)
        ->assertJsonPath('data.room', null);

    expect(AuditEventRecord::query()->where('action', 'providers.profile_updated')->where('object_id', $providerId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'provider_specialties.created')->where('object_id', $cardiologyId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'providers.specialties_set')->where('object_id', $providerId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'providers.license_added')->where('object_id', $providerId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'providers.license_removed')->where('object_id', $providerId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'provider_groups.created')->where('object_id', $groupId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'provider_groups.members_updated')->where('object_id', $groupId)->exists())->toBeTrue();
});

it('enforces provider extension permissions and validation rules', function (): void {
    User::factory()->create([
        'email' => 'provider.extensions.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'provider.extensions.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'provider.extensions.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'provider.extensions.admin+2@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'provider.extensions.viewer+2@openai.com');
    $blockedToken = providerIssueBearerToken($this, 'provider.extensions.blocked@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Provider Extensions Beta')->json('data.id');
    $clinicId = providerCreateClinic($this, $adminToken, $tenantId, 'beta-provider-ext', 'Provider Extensions Beta Clinic')->json('data.id');

    providerGrantPermissions($viewer, $tenantId, ['providers.view']);
    providerEnsureMembership($blocked, $tenantId);

    $providerId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Malika',
            'last_name' => 'Usmanova',
            'provider_type' => 'doctor',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId.'/profile')
        ->assertOk()
        ->assertJsonPath('data.is_accepting_new_patients', true)
        ->assertJsonPath('data.languages', []);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/providers/'.$providerId.'/profile', [
            'professional_title' => 'Blocked',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/specialties', [
            'name' => 'Denied',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/provider-groups')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $departmentId = providerCreateDepartment($this, $adminToken, $tenantId, $clinicId, 'neuro', 'Neurology')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/providers/'.$providerId.'/profile', [
            'department_id' => $departmentId,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $cardiologyId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/specialties', [
            'name' => 'Cardiology',
        ])
        ->assertCreated()
        ->json('data.id');

    $pediatricsId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/specialties', [
            'name' => 'Pediatrics',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/specialties', [
            'name' => 'cardiology',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/providers/'.$providerId.'/specialties', [
            'specialties' => [
                ['specialty_id' => $cardiologyId, 'is_primary' => true],
                ['specialty_id' => $pediatricsId, 'is_primary' => true],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/providers/'.$providerId.'/specialties', [
            'specialties' => [
                ['specialty_id' => $cardiologyId, 'is_primary' => true],
            ],
        ])
        ->assertOk();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/specialties/'.$cardiologyId)
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers/'.$providerId.'/licenses', [
            'license_type' => 'Medical License',
            'license_number' => 'ML-100',
            'issuing_authority' => 'Medical Board',
        ])
        ->assertCreated();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers/'.$providerId.'/licenses', [
            'license_type' => 'medical license',
            'license_number' => 'ML-100',
            'issuing_authority' => 'Medical Board',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/provider-groups', [
            'name' => 'Bad Group',
            'clinic_id' => '11111111-1111-1111-1111-111111111111',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $groupId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/provider-groups', [
            'name' => 'Evening Team',
            'clinic_id' => $clinicId,
        ])
        ->assertCreated()
        ->json('data.id');

    $otherTenantId = providerCreateTenant($this, $adminToken, 'Provider Extensions Gamma')->json('data.id');
    $otherProviderId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Outside',
            'last_name' => 'Tenant',
            'provider_type' => 'doctor',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/provider-groups/'.$groupId.'/members', [
            'provider_ids' => [$providerId, $otherProviderId],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');
});
