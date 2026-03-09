<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('returns patient summary timeline and export artifacts from tenant-scoped patient activity', function (): void {
    Storage::fake('exports');

    User::factory()->create([
        'email' => 'patient.read.admin@openai.com',
        'password' => 'secret-password',
    ]);

    $token = patientIssueBearerToken($this, 'patient.read.admin@openai.com');
    $tenantId = patientCreateTenant($this, $token, 'Patient Read Tenant')->json('data.id');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-09 09:00:00'));

    $patientId = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'preferred_name' => 'Azi',
            'sex' => 'female',
            'birth_date' => '1990-05-01',
            'email' => 'aziza@openai.com',
            'phone' => '+998901234567',
            'city_code' => 'tashkent',
            'district_code' => 'mirzo-ulugbek',
            'address_line_1' => '12 Amir Temur Street',
            'postal_code' => '100000',
        ])
        ->assertCreated()
        ->json('data.id');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-09 09:15:00'));

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/patients/'.$patientId, [
            'preferred_name' => 'Dr. Azi',
        ])
        ->assertOk();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/summary')
        ->assertOk()
        ->assertJsonPath('data.patient.id', $patientId)
        ->assertJsonPath('data.display_name', 'Dr. Azi Karimova')
        ->assertJsonPath('data.initials', 'DK')
        ->assertJsonPath('data.age_years', 35)
        ->assertJsonPath('data.directory_status', 'active')
        ->assertJsonPath('data.contact.has_email', true)
        ->assertJsonPath('data.contact.has_phone', true)
        ->assertJsonPath('data.location.city_code', 'tashkent')
        ->assertJsonPath('data.timeline_event_count', 2)
        ->assertJsonPath('data.last_activity_at', '2026-03-09T09:15:00+00:00');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/timeline?limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'patients.updated')
        ->assertJsonPath('meta.limit', 1);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-09 09:30:00'));

    $exportResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/export?sex=female&has_email=true')
        ->assertOk()
        ->assertJsonPath('status', 'patient_export_created')
        ->assertJsonPath('data.row_count', 1)
        ->assertJsonPath('data.format', 'csv')
        ->assertJsonPath('data.filters.sex', 'female')
        ->assertJsonPath('data.filters.has_email', true);

    $path = $exportResponse->json('data.storage.path');
    Storage::disk('exports')->assertExists($path);

    $contents = Storage::disk('exports')->get($path);

    expect($contents)->toContain('display_name');
    expect($contents)->toContain('Dr. Azi Karimova');
    expect(AuditEventRecord::query()->where('action', 'patients.exported')->exists())->toBeTrue();
});

it('enforces patient view access and tenant scope on summary timeline and export', function (): void {
    Storage::fake('exports');

    User::factory()->create([
        'email' => 'patient.read.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.read.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'patient.read.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.read.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.read.viewer@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'patient.read.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Read Tenant Two')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Bekzod',
            'last_name' => 'Nazarov',
            'sex' => 'male',
            'birth_date' => '1987-07-07',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/summary')
        ->assertOk()
        ->assertJsonPath('data.patient.id', $patientId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/timeline')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/export')
        ->assertOk();

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/summary')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Patient Read Tenant Three')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/timeline')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
