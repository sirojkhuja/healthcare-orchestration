<?php

use App\Models\User;

require_once __DIR__.'/../Billing/BillingTestSupport.php';

function insuranceAddInvoiceItem(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    array $payload,
    string $idempotencyKey,
) {
    return billingAddInvoiceItem($testCase, $token, $tenantId, $invoiceId, $payload, $idempotencyKey);
}

function insuranceCreateClaim(
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
        ->postJson('/api/v1/claims', $payload);
}

function insuranceCreateInvoice(
    $testCase,
    string $token,
    string $tenantId,
    array $payload,
    string $idempotencyKey,
) {
    return billingCreateInvoice($testCase, $token, $tenantId, $payload, $idempotencyKey);
}

function insuranceCreatePatient($testCase, string $token, string $tenantId, array $overrides = [])
{
    return billingCreatePatient($testCase, $token, $tenantId, $overrides);
}

function insuranceCreatePayer(
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
        ->postJson('/api/v1/insurance/payers', $payload);
}

function insuranceCreateRule(
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
        ->postJson('/api/v1/insurance/rules', $payload);
}

function insuranceCreateService(
    $testCase,
    string $token,
    string $tenantId,
    array $payload,
    string $idempotencyKey,
) {
    return billingCreateService($testCase, $token, $tenantId, $payload, $idempotencyKey);
}

function insuranceCreateTenant($testCase, string $token, string $name)
{
    return billingCreateTenant($testCase, $token, $name);
}

function insuranceDeleteClaim(
    $testCase,
    string $token,
    string $tenantId,
    string $claimId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->deleteJson('/api/v1/claims/'.$claimId);
}

function insuranceGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    billingGrantPermissions($user, $tenantId, $permissions);
}

function insuranceIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return billingIssueBearerToken($testCase, $email, $password);
}

function insuranceIssueInvoice(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    string $idempotencyKey,
) {
    return billingIssueInvoice($testCase, $token, $tenantId, $invoiceId, $idempotencyKey);
}

function insuranceMutatePatientInsurance(
    $testCase,
    string $token,
    string $tenantId,
    string $patientId,
    array $payload,
) {
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients/'.$patientId.'/insurance', $payload);
}

function insurancePostClaimAction(
    $testCase,
    string $token,
    string $tenantId,
    string $claimId,
    string $action,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/claims/'.$claimId.':'.$action, $payload);
}

function insuranceUploadClaimAttachment(
    $testCase,
    string $token,
    string $tenantId,
    string $claimId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->post('/api/v1/claims/'.$claimId.'/attachments', $payload);
}
