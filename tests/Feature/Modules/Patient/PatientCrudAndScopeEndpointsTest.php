<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('creates lists updates and soft deletes patients while updating tenant usage', function (): void {
    User::factory()->create([
        'email' => 'patient.admin+1@openai.com',
        'password' => 'secret-password',
    ]);

    $token = patientIssueBearerToken($this, 'patient.admin+1@openai.com');
    $tenantId = patientCreateTenant($this, $token, 'Patient Tenant Alpha')->json('data.id');

    $createResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'middle_name' => 'Anvarovna',
            'preferred_name' => 'Azi',
            'sex' => 'female',
            'birth_date' => '1990-05-01',
            'national_id' => 'aa1234567',
            'email' => 'aziza@openai.com',
            'phone' => '+998901234567',
            'city_code' => 'tashkent',
            'district_code' => 'mirzo-ulugbek',
            'address_line_1' => '12 Amir Temur Street',
            'postal_code' => '100000',
            'notes' => 'Prefers morning visits.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'patient_created')
        ->assertJsonPath('data.first_name', 'Aziza')
        ->assertJsonPath('data.preferred_name', 'Azi')
        ->assertJsonPath('data.national_id', 'AA1234567')
        ->assertJsonPath('data.email', 'aziza@openai.com')
        ->assertJsonPath('data.deleted_at', null);

    $patientId = $createResponse->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $patientId);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId)
        ->assertOk()
        ->assertJsonPath('data.birth_date', '1990-05-01')
        ->assertJsonPath('data.city_code', 'tashkent');

    $this->withToken($token)
        ->getJson('/api/v1/tenants/'.$tenantId.'/usage')
        ->assertOk()
        ->assertJsonPath('data.patients.used', 1);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/patients/'.$patientId, [
            'preferred_name' => 'Dr. Azi',
            'district_code' => 'yunusabad',
            'phone' => null,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'patient_updated')
        ->assertJsonPath('data.preferred_name', 'Dr. Azi')
        ->assertJsonPath('data.district_code', 'yunusabad')
        ->assertJsonPath('data.phone', null);

    $deleteResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/patients/'.$patientId)
        ->assertOk()
        ->assertJsonPath('status', 'patient_deleted')
        ->assertJsonPath('data.id', $patientId);

    expect($deleteResponse->json('data.deleted_at'))->not->toBeNull();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withToken($token)
        ->getJson('/api/v1/tenants/'.$tenantId.'/usage')
        ->assertOk()
        ->assertJsonPath('data.patients.used', 0);

    expect(DB::table('patients')->where('id', $patientId)->value('deleted_at'))->not->toBeNull();
    expect(AuditEventRecord::query()->where('action', 'patients.created')->where('object_id', $patientId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'patients.updated')->where('object_id', $patientId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'patients.deleted')->where('object_id', $patientId)->exists())->toBeTrue();
});

it('enforces patient permissions and tenant scoping', function (): void {
    $admin = User::factory()->create([
        'email' => 'patient.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'patient.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.viewer@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'patient.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Tenant Beta')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Jahongir',
            'last_name' => 'Rahimov',
            'sex' => 'male',
            'birth_date' => '1984-11-13',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId)
        ->assertOk()
        ->assertJsonPath('data.id', $patientId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Denied',
            'last_name' => 'Writer',
            'sex' => 'unknown',
            'birth_date' => '1999-01-01',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/patients/'.$patientId, [
            'preferred_name' => 'Denied',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Patient Tenant Gamma')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/patients/'.$patientId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
