<?php

use App\Models\User;

require_once __DIR__.'/../Scheduling/SchedulingTestSupport.php';

function treatmentCreatePatient($testCase, string $token, string $tenantId, array $overrides = [])
{
    return schedulingCreatePatient($testCase, $token, $tenantId, $overrides);
}

function treatmentCreateEncounter($testCase, string $token, string $tenantId, array $overrides = [])
{
    $patientId = $overrides['patient_id'] ?? treatmentCreatePatient($testCase, $token, $tenantId)->json('data.id');
    $providerId = $overrides['provider_id'] ?? treatmentCreateProvider($testCase, $token, $tenantId)->json('data.id');

    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/encounters', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'treatment_plan_id' => $overrides['treatment_plan_id'] ?? null,
            'appointment_id' => $overrides['appointment_id'] ?? null,
            'clinic_id' => $overrides['clinic_id'] ?? null,
            'room_id' => $overrides['room_id'] ?? null,
            'encountered_at' => $overrides['encountered_at'] ?? '2026-03-10T09:00:00+05:00',
            'timezone' => $overrides['timezone'] ?? 'Asia/Tashkent',
            'chief_complaint' => $overrides['chief_complaint'] ?? null,
            'summary' => $overrides['summary'] ?? null,
            'notes' => $overrides['notes'] ?? null,
            'follow_up_instructions' => $overrides['follow_up_instructions'] ?? null,
        ]);
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
