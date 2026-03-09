<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('manages patient contacts and tags with normalization ordering and audit coverage', function (): void {
    User::factory()->create([
        'email' => 'patient.contacts.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.contacts.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.contacts.admin@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.contacts.viewer@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Contacts Tenant')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Malika',
            'last_name' => 'Tursunova',
            'sex' => 'female',
            'birth_date' => '1991-01-15',
        ])
        ->assertCreated()
        ->json('data.id');

    $firstContactId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/contacts', [
            'name' => 'Laylo Tursunova',
            'relationship' => 'Sister',
            'email' => 'LAYLO@OPENAI.COM',
            'is_primary' => true,
            'notes' => 'Emergency family contact.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'patient_contact_created')
        ->assertJsonPath('data.email', 'laylo@openai.com')
        ->assertJsonPath('data.is_primary', true)
        ->json('data.id');

    $secondContactId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/contacts', [
            'name' => 'Rustam Tursunov',
            'relationship' => 'Brother',
            'phone' => ' +998 90 555 55 55 ',
            'is_primary' => true,
            'is_emergency' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.phone', '+998 90 555 55 55')
        ->assertJsonPath('data.is_primary', true)
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/contacts')
        ->assertOk()
        ->assertJsonPath('data.0.id', $secondContactId)
        ->assertJsonPath('data.0.is_primary', true)
        ->assertJsonPath('data.1.id', $firstContactId)
        ->assertJsonPath('data.1.is_primary', false);

    expect((bool) DB::table('patient_contacts')->where('id', $firstContactId)->value('is_primary'))->toBeFalse();

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/patients/'.$patientId.'/contacts/'.$firstContactId, [
            'phone' => '+998901111111',
            'is_emergency' => true,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'patient_contact_updated')
        ->assertJsonPath('data.phone', '+998901111111')
        ->assertJsonPath('data.is_emergency', true);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/patients/'.$patientId.'/tags', [
            'tags' => ['  Chronic   Care ', 'VIP', 'vip', '', 'Needs Follow Up'],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'patient_tags_updated')
        ->assertJsonPath('data.patient_id', $patientId)
        ->assertJsonPath('data.tags.0', 'chronic care')
        ->assertJsonPath('data.tags.1', 'needs follow up')
        ->assertJsonPath('data.tags.2', 'vip');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/tags')
        ->assertOk()
        ->assertJsonPath('data.tags.0', 'chronic care')
        ->assertJsonPath('data.tags.2', 'vip');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/patients/'.$patientId.'/contacts/'.$secondContactId)
        ->assertOk()
        ->assertJsonPath('status', 'patient_contact_deleted')
        ->assertJsonPath('data.id', $secondContactId);

    expect(AuditEventRecord::query()->where('action', 'patients.contact_created')->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'patients.contact_updated')->where('object_id', $patientId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'patients.contact_deleted')->where('object_id', $patientId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'patients.tags_updated')->where('object_id', $patientId)->exists())->toBeTrue();
});

it('enforces patient contact and tag permissions tenant scoping and channel validation', function (): void {
    User::factory()->create([
        'email' => 'patient.contacts.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.contacts.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'patient.contacts.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.contacts.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.contacts.viewer+2@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'patient.contacts.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Contacts Tenant Two')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Bekzod',
            'last_name' => 'Qodirov',
            'sex' => 'male',
            'birth_date' => '1988-09-09',
        ])
        ->assertCreated()
        ->json('data.id');

    $contactId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/contacts', [
            'name' => 'Azamat Qodirov',
            'phone' => '+998901234567',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/contacts')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/tags')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/contacts', [
            'name' => 'Denied Contact',
            'phone' => '+998900000000',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/patients/'.$patientId.'/tags', [
            'tags' => ['denied'],
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/contacts')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/patients/'.$patientId.'/contacts/'.$contactId, [
            'phone' => null,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Patient Contacts Tenant Three')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/contacts')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
