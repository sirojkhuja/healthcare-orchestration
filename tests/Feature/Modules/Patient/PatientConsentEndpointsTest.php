<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('creates lists and revokes patient consents with derived statuses ordering and audit coverage', function (): void {
    User::factory()->create([
        'email' => 'patient.consents.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.consents.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.consents.admin@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.consents.viewer@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Consents Tenant')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Nilufar',
            'last_name' => 'Karimova',
            'sex' => 'female',
            'birth_date' => '1992-04-06',
        ])
        ->assertCreated()
        ->json('data.id');

    $telemedicineConsentId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'Telemedicine Consent',
            'granted_by_name' => 'Nilufar Karimova',
            'expires_at' => '2026-12-31T23:59:59+00:00',
            'notes' => 'Signed at reception.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'patient_consent_created')
        ->assertJsonPath('data.consent_type', 'telemedicine_consent')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.granted_by_name', 'Nilufar Karimova')
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'Telemedicine Consent',
            'granted_by_name' => 'Nilufar Karimova',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $sharingConsentId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'Research Sharing',
            'granted_by_name' => 'Dr. Aziza Karimova',
            'granted_by_relationship' => 'Attending Physician',
            'granted_at' => '2026-03-10T08:05:00+00:00',
        ])
        ->assertCreated()
        ->assertJsonPath('data.consent_type', 'research_sharing')
        ->assertJsonPath('data.status', 'active')
        ->json('data.id');

    DB::table('patient_consents')
        ->where('id', $telemedicineConsentId)
        ->update([
            'granted_at' => '2026-03-01 08:00:00+00:00',
            'expires_at' => '2026-03-01 08:10:00+00:00',
            'updated_at' => '2026-03-10 08:11:00+00:00',
        ]);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/consents')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $sharingConsentId)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.1.id', $telemedicineConsentId)
        ->assertJsonPath('data.1.status', 'expired');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents/'.$sharingConsentId.':revoke', [
            'reason' => 'Patient withdrew approval.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'patient_consent_revoked')
        ->assertJsonPath('data.id', $sharingConsentId)
        ->assertJsonPath('data.status', 'revoked')
        ->assertJsonPath('data.revocation_reason', 'Patient withdrew approval.');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents/'.$sharingConsentId.':revoke', [
            'reason' => 'Duplicate revoke.',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    expect(AuditEventRecord::query()->where('action', 'patients.consent_created')->where('object_id', $patientId)->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'patients.consent_revoked')->where('object_id', $patientId)->exists())->toBeTrue();
});

it('enforces consent permissions tenant scope and expiry validation', function (): void {
    User::factory()->create([
        'email' => 'patient.consents.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.consents.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'patient.consents.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.consents.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.consents.viewer+2@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'patient.consents.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Consents Tenant Two')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Diyor',
            'last_name' => 'Rahmonov',
            'sex' => 'male',
            'birth_date' => '1989-08-08',
        ])
        ->assertCreated()
        ->json('data.id');

    $consentId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'privacy_disclosure',
            'granted_by_name' => 'Diyor Rahmonov',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/consents')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'denied_consent',
            'granted_by_name' => 'Denied Writer',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents/'.$consentId.':revoke')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/consents')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'invalid_expiry',
            'granted_by_name' => 'Diyor Rahmonov',
            'granted_at' => '2026-03-10T09:00:00+00:00',
            'expires_at' => '2026-03-10T08:59:00+00:00',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Patient Consents Tenant Three')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/consents')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents/'.$consentId.':revoke')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
