<?php

require_once __DIR__.'/../Provider/ProviderTestSupport.php';

function schedulingCreateTenant($testCase, string $token, string $name)
{
    return providerCreateTenant($testCase, $token, $name);
}

function schedulingCreateAppointment(
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
        ->postJson('/api/v1/appointments', $payload);
}

function schedulingAppointmentAction(
    $testCase,
    string $token,
    string $tenantId,
    string $appointmentId,
    string $action,
    string $idempotencyKey,
    array $payload = [],
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/appointments/'.$appointmentId.':'.$action, $payload);
}

function schedulingCreateWaitlistEntry(
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
        ->postJson('/api/v1/waitlist', $payload);
}

function schedulingIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return providerIssueBearerToken($testCase, $email, $password);
}

function schedulingCreatePatient($testCase, string $token, string $tenantId, array $overrides = [])
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', $overrides + [
            'first_name' => 'Dilnoza',
            'last_name' => 'Saidova',
            'sex' => 'female',
            'birth_date' => '1994-04-12',
        ])
        ->assertCreated();
}

function schedulingCreateProvider($testCase, string $token, string $tenantId, array $overrides = [])
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/providers', $overrides + [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'provider_type' => 'doctor',
        ])
        ->assertCreated();
}

function schedulingCreateRule(
    $testCase,
    string $token,
    string $tenantId,
    string $providerId,
    array $payload,
    string $idempotencyKey,
) {
    return $testCase->withToken($token)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => $idempotencyKey,
        ])
        ->postJson('/api/v1/providers/'.$providerId.'/availability/rules', $payload);
}

function schedulingUpdateClinicSettings($testCase, string $token, string $tenantId, string $clinicId, array $overrides = [])
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/clinics/'.$clinicId.'/settings', $overrides + [
            'timezone' => 'Asia/Tashkent',
            'default_appointment_duration_minutes' => 30,
            'slot_interval_minutes' => 15,
            'allow_walk_ins' => true,
            'require_appointment_confirmation' => false,
            'telemedicine_enabled' => false,
        ])
        ->assertOk();
}

function schedulingUpdateClinicWorkHours($testCase, string $token, string $tenantId, string $clinicId, array $days)
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/clinics/'.$clinicId.'/work-hours', [
            'days' => $days,
        ])
        ->assertOk();
}

function schedulingUpdateTenantSettings($testCase, string $token, string $tenantId, array $overrides = [])
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/tenants/'.$tenantId.'/settings', $overrides + [
            'locale' => 'en',
            'timezone' => 'Asia/Tashkent',
            'currency' => 'UZS',
        ])
        ->assertOk();
}
