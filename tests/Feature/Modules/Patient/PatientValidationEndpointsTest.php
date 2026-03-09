<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('validates patient location rules and tenant scoped national id uniqueness', function (): void {
    User::factory()->create([
        'email' => 'patient.admin+3@openai.com',
        'password' => 'secret-password',
    ]);

    $token = patientIssueBearerToken($this, 'patient.admin+3@openai.com');
    $tenantId = patientCreateTenant($this, $token, 'Patient Tenant Delta')->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Dilshod',
            'last_name' => 'Usmanov',
            'sex' => 'male',
            'birth_date' => '1988-08-08',
            'district_code' => 'yunusabad',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Nodira',
            'last_name' => 'Saidova',
            'sex' => 'female',
            'birth_date' => '1992-04-17',
            'national_id' => 'bb7654321',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Nodira',
            'last_name' => 'Saidova Duplicate',
            'sex' => 'female',
            'birth_date' => '1993-04-17',
            'national_id' => 'bb7654321',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $otherTenantId = patientCreateTenant($this, $token, 'Patient Tenant Epsilon')->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Nodira',
            'last_name' => 'Saidova',
            'sex' => 'female',
            'birth_date' => '1992-04-17',
            'national_id' => 'bb7654321',
        ])
        ->assertCreated();
});
