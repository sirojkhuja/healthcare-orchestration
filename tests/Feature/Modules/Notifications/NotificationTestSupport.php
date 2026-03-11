<?php

use App\Models\User;

require_once __DIR__.'/../Treatment/TreatmentTestSupport.php';

function notificationCreateTemplate(
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
        ->postJson('/api/v1/templates', $payload);
}

function notificationCreateTenant($testCase, string $token, string $name)
{
    return treatmentCreateTenant($testCase, $token, $name);
}

function notificationDeleteTemplate(
    $testCase,
    string $token,
    string $tenantId,
    string $templateId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->deleteJson('/api/v1/templates/'.$templateId);
}

function notificationGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    treatmentGrantPermissions($user, $tenantId, $permissions);
}

function notificationIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return treatmentIssueBearerToken($testCase, $email, $password);
}

function notificationTestRenderTemplate(
    $testCase,
    string $token,
    string $tenantId,
    string $templateId,
    array $payload,
) {
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/templates/'.$templateId.':test-render', $payload);
}

function notificationUpdateTemplate(
    $testCase,
    string $token,
    string $tenantId,
    string $templateId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->patchJson('/api/v1/templates/'.$templateId, $payload);
}
