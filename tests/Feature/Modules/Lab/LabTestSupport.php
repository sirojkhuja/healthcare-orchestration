<?php

use App\Models\User;

require_once __DIR__.'/../Treatment/TreatmentTestSupport.php';

function labCreateEncounter($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreateEncounter($testCase, $token, $tenantId, $overrides);
}

function labCreateLabOrder(
    $testCase,
    string $token,
    string $tenantId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/lab-orders', $payload);
}

function labCreateLabTest($testCase, string $token, string $tenantId, array $overrides = [])
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/lab-tests', $overrides + [
            'code' => 'cbc',
            'name' => 'Complete Blood Count',
            'specimen_type' => 'blood',
            'result_type' => 'numeric',
            'lab_provider_key' => 'mock-lab',
            'unit' => '10^9/L',
            'reference_range' => '4.0-11.0',
        ])
        ->assertCreated();
}

function labCreatePatient($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreatePatient($testCase, $token, $tenantId, $overrides);
}

function labCreatePlan($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreatePlan($testCase, $token, $tenantId, $overrides);
}

function labCreateProvider($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreateProvider($testCase, $token, $tenantId, $overrides);
}

function labCreateTenant($testCase, string $token, string $name)
{
    return treatmentCreateTenant($testCase, $token, $name);
}

function labEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
{
    treatmentEnsureMembership($user, $tenantId, $status);
}

function labGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    treatmentGrantPermissions($user, $tenantId, $permissions);
}

function labIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return treatmentIssueBearerToken($testCase, $email, $password);
}
