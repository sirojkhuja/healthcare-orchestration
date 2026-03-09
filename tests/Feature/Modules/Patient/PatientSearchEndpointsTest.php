<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('searches active patients by relevance and structured directory filters', function (): void {
    User::factory()->create([
        'email' => 'patient.search.admin@openai.com',
        'password' => 'secret-password',
    ]);

    $token = patientIssueBearerToken($this, 'patient.search.admin@openai.com');
    $tenantId = patientCreateTenant($this, $token, 'Patient Search Tenant')->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'preferred_name' => 'Azi',
            'sex' => 'female',
            'birth_date' => '1990-05-01',
            'national_id' => 'AA1234567',
            'email' => 'aziza@openai.com',
            'city_code' => 'tashkent',
        ])
        ->assertCreated();

    $supportId = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Madina',
            'last_name' => 'Abdullaeva',
            'sex' => 'female',
            'birth_date' => '1995-02-11',
            'email' => 'aziza.helper@openai.com',
            'city_code' => 'tashkent',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Rustam',
            'last_name' => 'Bekov',
            'sex' => 'male',
            'birth_date' => '1988-12-09',
            'phone' => '+998901234000',
            'city_code' => 'samarkand',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/search?q=aziza')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.first_name', 'Aziza')
        ->assertJsonPath('data.1.id', $supportId)
        ->assertJsonPath('meta.filters.q', 'aziza')
        ->assertJsonPath('meta.filters.limit', 25);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/search?sex=female&city_code=tashkent&has_email=true&limit=5')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.filters.sex', 'female')
        ->assertJsonPath('meta.filters.has_email', true)
        ->assertJsonPath('meta.filters.limit', 5);
});

it('validates patient search ranges and enforces patient view permission', function (): void {
    User::factory()->create([
        'email' => 'patient.search.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.search.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'patient.search.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.search.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.search.viewer@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'patient.search.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Search Tenant Two')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);
    patientEnsureMembership($blocked, $tenantId);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Safiya',
            'last_name' => 'Karimova',
            'sex' => 'female',
            'birth_date' => '1993-03-07',
        ])
        ->assertCreated();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/search?q=safiya')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/search?q=safiya')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/search?birth_date_from=2026-01-01&birth_date_to=2025-01-01')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');
});
