<?php

use App\Models\User;

require_once __DIR__.'/../Scheduling/SchedulingTestSupport.php';

function treatmentCreatePatient($testCase, string $token, string $tenantId, array $overrides = [])
{
    return schedulingCreatePatient($testCase, $token, $tenantId, $overrides);
}

function treatmentCreatePlan($testCase, string $token, string $tenantId, array $overrides = [])
{
    $patientId = $overrides['patient_id'] ?? treatmentCreatePatient($testCase, $token, $tenantId)->json('data.id');
    $providerId = $overrides['provider_id'] ?? treatmentCreateProvider($testCase, $token, $tenantId)->json('data.id');

    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/treatment-plans', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'title' => $overrides['title'] ?? 'Default treatment plan',
            'summary' => $overrides['summary'] ?? null,
            'goals' => $overrides['goals'] ?? null,
            'planned_start_date' => $overrides['planned_start_date'] ?? null,
            'planned_end_date' => $overrides['planned_end_date'] ?? null,
        ]);
}

function treatmentCreateProvider($testCase, string $token, string $tenantId, array $overrides = [])
{
    return schedulingCreateProvider($testCase, $token, $tenantId, $overrides);
}

function treatmentCreateTenant($testCase, string $token, string $name)
{
    return schedulingCreateTenant($testCase, $token, $name);
}

function treatmentEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
{
    patientEnsureMembership($user, $tenantId, $status);
}

function treatmentGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    patientGrantPermissions($user, $tenantId, $permissions);
}

function treatmentIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return schedulingIssueBearerToken($testCase, $email, $password);
}
