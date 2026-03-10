<?php

use App\Models\User;

require_once __DIR__.'/../Treatment/TreatmentTestSupport.php';

function billingCreatePriceList(
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
        ->postJson('/api/v1/price-lists', $payload);
}

function billingCreateService(
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
        ->postJson('/api/v1/services', $payload);
}

function billingCreateTenant($testCase, string $token, string $name)
{
    return treatmentCreateTenant($testCase, $token, $name);
}

function billingDeletePriceList(
    $testCase,
    string $token,
    string $tenantId,
    string $priceListId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->deleteJson('/api/v1/price-lists/'.$priceListId);
}

function billingDeleteService(
    $testCase,
    string $token,
    string $tenantId,
    string $serviceId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->deleteJson('/api/v1/services/'.$serviceId);
}

function billingEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
{
    treatmentEnsureMembership($user, $tenantId, $status);
}

function billingGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    treatmentGrantPermissions($user, $tenantId, $permissions);
}

function billingIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return treatmentIssueBearerToken($testCase, $email, $password);
}

function billingSetPriceListItems(
    $testCase,
    string $token,
    string $tenantId,
    string $priceListId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->putJson('/api/v1/price-lists/'.$priceListId.'/items', $payload);
}

function billingUpdatePriceList(
    $testCase,
    string $token,
    string $tenantId,
    string $priceListId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->patchJson('/api/v1/price-lists/'.$priceListId, $payload);
}

function billingUpdateService(
    $testCase,
    string $token,
    string $tenantId,
    string $serviceId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->patchJson('/api/v1/services/'.$serviceId, $payload);
}
