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

function providerCreateDepartment($testCase, string $token, string $tenantId, string $clinicId, string $code, string $name)
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.'/departments', [
            'code' => $code,
            'name' => $name,
        ])
        ->assertCreated();
}

function providerCreateRoom(
    $testCase,
    string $token,
    string $tenantId,
    string $clinicId,
    string $departmentId,
    string $code,
    string $name,
) {
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.'/rooms', [
            'department_id' => $departmentId,
            'code' => $code,
            'name' => $name,
            'type' => 'consultation',
            'capacity' => 1,
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
