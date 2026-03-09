<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/ProviderTestSupport.php';

uses(RefreshDatabase::class);

it('creates lists updates and soft deletes providers while updating tenant usage', function (): void {
    User::factory()->create([
        'email' => 'provider.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'provider.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'provider.admin@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'provider.viewer@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Provider Tenant Alpha')->json('data.id');
    $clinicId = providerCreateClinic($this, $adminToken, $tenantId, 'alpha-provider', 'Provider Clinic')->json('data.id');

    providerGrantPermissions($viewer, $tenantId, ['providers.view']);

    $createResponse = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'middle_name' => 'Akmalovna',
            'provider_type' => 'doctor',
            'email' => 'AZIZA@OPENAI.COM',
            'phone' => ' +998901112233 ',
            'clinic_id' => $clinicId,
            'notes' => 'Lead physician.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'provider_created')
        ->assertJsonPath('data.email', 'aziza@openai.com')
        ->assertJsonPath('data.phone', '+998901112233')
        ->assertJsonPath('data.clinic_id', $clinicId);

    $providerId = $createResponse->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $providerId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId)
        ->assertOk()
        ->assertJsonPath('data.provider_type', 'doctor');

    $this->withToken($adminToken)
        ->getJson('/api/v1/tenants/'.$tenantId.'/usage')
        ->assertOk()
        ->assertJsonPath('data.providers.used', 1);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/providers/'.$providerId, [
            'preferred_name' => 'Dr. Azi',
            'provider_type' => 'other',
            'clinic_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'provider_updated')
        ->assertJsonPath('data.preferred_name', 'Dr. Azi')
        ->assertJsonPath('data.provider_type', 'other')
        ->assertJsonPath('data.clinic_id', null);

    $deleteResponse = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/providers/'.$providerId)
        ->assertOk()
        ->assertJsonPath('status', 'provider_deleted')
        ->assertJsonPath('data.id', $providerId);

    expect($deleteResponse->json('data.deleted_at'))->not->toBeNull();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers/'.$providerId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->withToken($adminToken)
        ->getJson('/api/v1/tenants/'.$tenantId.'/usage')
        ->assertOk()
        ->assertJsonPath('data.providers.used', 0);

    expect(AuditEventRecord::query()->where('action', 'providers.created')->where('object_id', $providerId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'providers.updated')->where('object_id', $providerId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'providers.deleted')->where('object_id', $providerId)->exists())->toBeTrue();
});

it('enforces provider permissions tenant scope and clinic validation', function (): void {
    User::factory()->create([
        'email' => 'provider.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'provider.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'provider.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = providerIssueBearerToken($this, 'provider.admin+2@openai.com');
    $viewerToken = providerIssueBearerToken($this, 'provider.viewer+2@openai.com');
    $blockedToken = providerIssueBearerToken($this, 'provider.blocked@openai.com');
    $tenantId = providerCreateTenant($this, $adminToken, 'Provider Tenant Beta')->json('data.id');

    providerGrantPermissions($viewer, $tenantId, ['providers.view']);
    providerEnsureMembership($blocked, $tenantId);

    $providerId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Bekzod',
            'last_name' => 'Nazarov',
            'provider_type' => 'nurse',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Denied',
            'last_name' => 'Writer',
            'provider_type' => 'doctor',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/providers/'.$providerId, [
            'preferred_name' => 'Denied',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/providers/'.$providerId)
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/providers')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', [
            'first_name' => 'Invalid',
            'last_name' => 'Clinic',
            'provider_type' => 'doctor',
            'clinic_id' => '11111111-1111-1111-1111-111111111111',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $otherTenantId = providerCreateTenant($this, $adminToken, 'Provider Tenant Gamma')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/providers/'.$providerId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
