<?php

use App\Models\User;

require_once __DIR__.'/../Treatment/TreatmentTestSupport.php';

function pharmacyCreateEncounter($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreateEncounter($testCase, $token, $tenantId, $overrides);
}

function pharmacyAddAllergy(
    $testCase,
    string $token,
    string $tenantId,
    string $patientId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/patients/'.$patientId.'/allergies', $payload);
}

function pharmacyCreatePatient($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreatePatient($testCase, $token, $tenantId, $overrides);
}

function pharmacyCreateMedication(
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
        ->postJson('/api/v1/medications', $payload);
}

function pharmacyCreatePlan($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreatePlan($testCase, $token, $tenantId, $overrides);
}

function pharmacyDeleteMedication(
    $testCase,
    string $token,
    string $tenantId,
    string $medicationId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->deleteJson('/api/v1/medications/'.$medicationId);
}

function pharmacyCreatePrescription(
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
        ->postJson('/api/v1/prescriptions', $payload);
}

function pharmacyCreateProvider($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreateProvider($testCase, $token, $tenantId, $overrides);
}

function pharmacyCreateTenant($testCase, string $token, string $name)
{
    return treatmentCreateTenant($testCase, $token, $name);
}

function pharmacyCreateTreatmentItem($testCase, string $token, string $tenantId, string $planId, array $overrides = [])
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans/'.$planId.'/items', $overrides + [
            'item_type' => 'medication',
            'title' => 'Default medication item',
        ]);
}

function pharmacyEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
{
    treatmentEnsureMembership($user, $tenantId, $status);
}

function pharmacyGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    treatmentGrantPermissions($user, $tenantId, $permissions);
}

function pharmacyIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return treatmentIssueBearerToken($testCase, $email, $password);
}

function pharmacyUpdateMedication(
    $testCase,
    string $token,
    string $tenantId,
    string $medicationId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->patchJson('/api/v1/medications/'.$medicationId, $payload);
}
