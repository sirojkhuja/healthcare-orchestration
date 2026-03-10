<?php

use App\Models\User;

require_once __DIR__.'/../Scheduling/SchedulingTestSupport.php';

function treatmentCreatePatient($testCase, string $token, string $tenantId, array $overrides = [])
{
    return schedulingCreatePatient($testCase, $token, $tenantId, $overrides);
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
