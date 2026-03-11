<?php

use App\Models\User;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Data\RequestMetadata;
use Illuminate\Support\Str;

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

function billingCreatePatient($testCase, string $token, string $tenantId, array $overrides = [])
{
    return treatmentCreatePatient($testCase, $token, $tenantId, $overrides);
}

function billingCreateInvoice(
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
        ->postJson('/api/v1/invoices', $payload);
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

function billingDeleteInvoice(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->deleteJson('/api/v1/invoices/'.$invoiceId);
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

function billingAddInvoiceItem(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/invoices/'.$invoiceId.'/items', $payload);
}

function billingEnsureMembership(User $user, string $tenantId, string $status = 'active'): void
{
    treatmentEnsureMembership($user, $tenantId, $status);
}

function billingGrantPermissions(User $user, string $tenantId, array $permissions): void
{
    treatmentGrantPermissions($user, $tenantId, $permissions);
}

function billingInitializeApplicationContext(
    $testCase,
    User $user,
    string $tenantId,
    string $sessionId = 'billing-test-session',
): void {
    $testCase->actingAs($user, 'api');
    request()->attributes->set('auth_session_id', $sessionId);

    app(RequestMetadataContext::class)->initialize(new RequestMetadata(
        requestId: (string) Str::uuid(),
        correlationId: (string) Str::uuid(),
        causationId: (string) Str::uuid(),
    ));
    app(TenantContext::class)->initialize($tenantId, 'test');
}

function billingIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return treatmentIssueBearerToken($testCase, $email, $password);
}

function billingIssueInvoice(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/invoices/'.$invoiceId.':issue');
}

function billingFinalizeInvoice(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/invoices/'.$invoiceId.':finalize');
}

function billingInitiatePayment(
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
        ->postJson('/api/v1/payments:initiate', $payload);
}

function billingCapturePayment(
    $testCase,
    string $token,
    string $tenantId,
    string $paymentId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/payments/'.$paymentId.':capture');
}

function billingCancelPayment(
    $testCase,
    string $token,
    string $tenantId,
    string $paymentId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/payments/'.$paymentId.':cancel', $payload);
}

function billingRefundPayment(
    $testCase,
    string $token,
    string $tenantId,
    string $paymentId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/payments/'.$paymentId.':refund', $payload);
}

function billingVoidInvoice(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/invoices/'.$invoiceId.':void', $payload);
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

function billingUpdateInvoice(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->patchJson('/api/v1/invoices/'.$invoiceId, $payload);
}

function billingUpdateInvoiceItem(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    string $itemId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->patchJson('/api/v1/invoices/'.$invoiceId.'/items/'.$itemId, $payload);
}

function billingDeleteInvoiceItem(
    $testCase,
    string $token,
    string $tenantId,
    string $invoiceId,
    string $itemId,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->deleteJson('/api/v1/invoices/'.$invoiceId.'/items/'.$itemId);
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
