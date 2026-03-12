<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Patient/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('lists and shows tenant-scoped compliance consent views with filters', function (): void {
    User::factory()->create([
        'email' => 'compliance.consents.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'compliance.consents.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'compliance.consents.admin@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'compliance.consents.viewer@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Compliance Consent Views')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['compliance.view']);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Malika',
            'last_name' => 'Nazarova',
            'preferred_name' => 'Mali',
            'sex' => 'female',
            'birth_date' => '1994-06-01',
        ])
        ->assertCreated()
        ->json('data.id');

    $expiredConsentId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'Research Sharing',
            'granted_by_name' => 'Mali Nazarova',
            'granted_at' => '2026-03-01T08:00:00+00:00',
            'expires_at' => '2026-03-05T08:00:00+00:00',
        ])
        ->assertCreated()
        ->json('data.id');

    $activeConsentId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'Privacy Disclosure',
            'granted_by_name' => 'Front Desk',
            'notes' => 'Signed on tablet.',
        ])
        ->assertCreated()
        ->json('data.id');

    $revokedConsentId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'Telemedicine',
            'granted_by_name' => 'Dr. Karimov',
            'granted_at' => '2026-03-10T09:00:00+00:00',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents/'.$revokedConsentId.':revoke', [
            'reason' => 'Withdrawn by patient.',
        ])
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/consents?limit=10')
        ->assertOk()
        ->assertJsonPath('data.0.id', $activeConsentId)
        ->assertJsonPath('data.0.patient.display_name', 'Mali Nazarova')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.1.id', $revokedConsentId)
        ->assertJsonPath('data.1.status', 'revoked');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/consents?status=expired')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $expiredConsentId)
        ->assertJsonPath('data.0.status', 'expired');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/consents?q=telemedicine')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $revokedConsentId)
        ->assertJsonPath('data.0.revocation_reason', 'Withdrawn by patient.');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/consents/'.$activeConsentId)
        ->assertOk()
        ->assertJsonPath('data.id', $activeConsentId)
        ->assertJsonPath('data.patient_id', $patientId)
        ->assertJsonPath('data.consent_type', 'privacy_disclosure');
});

it('enforces compliance consent view permissions and tenant scope', function (): void {
    User::factory()->create([
        'email' => 'compliance.consents.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'compliance.consents.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'compliance.consents.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'compliance.consents.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'compliance.consents.viewer+2@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'compliance.consents.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Compliance Consent Views Two')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['compliance.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Bekzod',
            'last_name' => 'Azimov',
            'sex' => 'male',
            'birth_date' => '1988-08-08',
        ])
        ->assertCreated()
        ->json('data.id');

    $consentId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/consents', [
            'consent_type' => 'privacy_disclosure',
            'granted_by_name' => 'Bekzod Azimov',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/consents')
        ->assertOk();

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/consents')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Compliance Consent Views Three')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/consents/'.$consentId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    expect(DB::table('patient_consents')->where('id', $consentId)->exists())->toBeTrue();
});
