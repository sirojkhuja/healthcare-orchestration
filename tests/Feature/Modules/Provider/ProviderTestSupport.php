<?php

use App\Models\User;

require_once __DIR__.'/../Patient/PatientTestSupport.php';

function providerCreateClinic($testCase, string $token, string $tenantId, string $code, string $name)
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics', [
            'code' => $code,
            'name' => $name,
            'city_code' => 'tashkent',
            'district_code' => 'yunusabad',
        ])
        ->assertCreated();
}

function providerCreateTenant($testCase, string $token, string $name)
{
    return patientCreateTenant($testCase, $token, $name);
}

function providerEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
{
    patientEnsureMembership($user, $tenantId, $status);
}

function providerGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    patientGrantPermissions($user, $tenantId, $permissions);
}

function providerIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return patientIssueBearerToken($testCase, $email, $password);
}
