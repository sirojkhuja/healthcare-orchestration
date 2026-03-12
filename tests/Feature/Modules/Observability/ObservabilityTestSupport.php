<?php

use App\Models\User;

require_once __DIR__.'/../Treatment/TreatmentTestSupport.php';

function observabilityCreateTenant($testCase, string $token, string $name)
{
    return treatmentCreateTenant($testCase, $token, $name);
}

function observabilityGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    treatmentGrantPermissions($user, $tenantId, $permissions);
}

function observabilityEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
{
    treatmentEnsureMembership($user, $tenantId, $status);
}

function observabilityIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return treatmentIssueBearerToken($testCase, $email, $password);
}
