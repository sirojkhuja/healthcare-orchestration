<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Patient/PatientTestSupport.php';
require_once __DIR__.'/../Provider/ProviderTestSupport.php';
require_once __DIR__.'/../Scheduling/SchedulingTestSupport.php';
require_once __DIR__.'/../Billing/BillingTestSupport.php';
require_once __DIR__.'/../Insurance/InsuranceTestSupport.php';

uses(RefreshDatabase::class);

it('lists shared reference catalogs and serves shared search routes with per-type permissions', function (): void {
    $admin = User::factory()->create([
        'email' => 'shared.search.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $limited = User::factory()->create([
        'email' => 'shared.search.limited@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'shared.search.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'shared.search.admin@openai.com');
    $limitedToken = patientIssueBearerToken($this, 'shared.search.limited@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'shared.search.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Shared Search Tenant')->json('data.id');

    patientGrantPermissions($admin, $tenantId, [
        'reference.view',
        'search.global',
        'patients.view',
        'patients.manage',
        'providers.view',
        'providers.manage',
        'appointments.view',
        'appointments.manage',
        'billing.view',
        'billing.manage',
        'claims.view',
        'claims.manage',
        'tenants.manage',
    ]);
    patientGrantPermissions($limited, $tenantId, [
        'search.global',
        'patients.view',
    ]);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'sex' => 'female',
            'birth_date' => '1990-05-01',
            'email' => 'aziza.shared@openai.com',
            'city_code' => 'tashkent',
        ])
        ->assertCreated()
        ->json('data.id');

    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId, [
        'first_name' => 'Kamola',
        'last_name' => 'Rasulova',
        'provider_type' => 'doctor',
        'email' => 'kamola.shared@openai.com',
    ])->json('data.id');

    $appointmentId = schedulingCreateAppointment(
        $this,
        $adminToken,
        $tenantId,
        [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'scheduled_start_at' => '2026-03-20T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-20T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'shared-search-appointment-create',
    )->json('data.id');

    $serviceId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'shared-consult',
        'name' => 'Shared Search Consultation',
        'category' => 'consultation',
        'unit' => 'visit',
    ], 'shared-search-service-create')
        ->assertCreated()
        ->json('data.id');

    $invoiceId = billingCreateInvoice($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
        'invoice_date' => '2026-03-12',
    ], 'shared-search-invoice-create')
        ->assertCreated()
        ->json('data.id');

    billingAddInvoiceItem($this, $adminToken, $tenantId, $invoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '150000',
    ], 'shared-search-invoice-item')
        ->assertCreated();

    insuranceIssueInvoice($this, $adminToken, $tenantId, $invoiceId, 'shared-search-invoice-issue')
        ->assertOk();

    $payerId = insuranceCreatePayer($this, $adminToken, $tenantId, [
        'code' => 'uhc',
        'name' => 'United Health',
        'insurance_code' => 'uhc-ppo',
    ], 'shared-search-payer-create')
        ->assertCreated()
        ->json('data.id');

    $policyId = insuranceMutatePatientInsurance($this, $adminToken, $tenantId, $patientId, [
        'insurance_code' => 'uhc-ppo',
        'policy_number' => 'POL-SEARCH-1',
        'member_number' => 'MEM-SEARCH-1',
        'effective_from' => '2026-03-01',
        'is_primary' => true,
    ])->assertCreated()->json('data.id');

    $claimId = insuranceCreateClaim($this, $adminToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'payer_id' => $payerId,
        'patient_policy_id' => $policyId,
        'billed_amount' => '150000',
        'service_date' => '2026-03-12',
    ], 'shared-search-claim-create')
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/reference/currencies?q=us&limit=2')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'USD')
        ->assertJsonPath('meta.filters.limit', 2);

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/reference/currencies')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/search/patients?q=aziza')
        ->assertOk()
        ->assertJsonPath('data.0.id', $patientId);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/search/providers?q=kamola')
        ->assertOk()
        ->assertJsonPath('data.0.id', $providerId)
        ->assertJsonPath('meta.filters.q', 'kamola');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/search/appointments?q=aziza')
        ->assertOk()
        ->assertJsonPath('data.0.id', $appointmentId);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/search/invoices?q=inv-')
        ->assertOk()
        ->assertJsonPath('data.0.id', $invoiceId);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/search/claims?q=clm-')
        ->assertOk()
        ->assertJsonPath('data.0.id', $claimId);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/search/global?q=aziza&types[]=patient&types[]=appointment')
        ->assertOk()
        ->assertJsonPath('data.patient.0.id', $patientId)
        ->assertJsonPath('data.appointment.0.id', $appointmentId)
        ->assertJsonPath('meta.filters.limit_per_type', 5)
        ->assertJsonPath('meta.returned_types.0', 'patient');

    $this->withToken($limitedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/search/global?q=aziza&types[]=patient&types[]=appointment')
        ->assertOk()
        ->assertJsonPath('data.patient.0.id', $patientId)
        ->assertJsonMissingPath('data.appointment')
        ->assertJsonPath('meta.returned_types', ['patient']);
});

it('manages report definitions runs downloads and deletes', function (): void {
    Storage::fake('artifacts');

    $manager = User::factory()->create([
        'email' => 'reports.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'reports.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = patientIssueBearerToken($this, 'reports.manager@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'reports.viewer@openai.com');
    $tenantId = patientCreateTenant($this, $managerToken, 'Reports Tenant')->json('data.id');

    patientGrantPermissions($manager, $tenantId, [
        'reports.view',
        'reports.manage',
        'patients.view',
        'patients.manage',
    ]);
    patientGrantPermissions($viewer, $tenantId, ['reports.view']);

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Aziza',
            'last_name' => 'Karimova',
            'sex' => 'female',
            'birth_date' => '1990-05-01',
            'email' => 'aziza.reports@openai.com',
        ])
        ->assertCreated();

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Bekzod',
            'last_name' => 'Nazarov',
            'sex' => 'male',
            'birth_date' => '1988-06-12',
        ])
        ->assertCreated();

    $reportId = $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'reports-create-1',
        ])
        ->postJson('/api/v1/reports', [
            'code' => 'Daily Patients',
            'name' => 'Daily Patient Directory',
            'description' => 'Patients with email addresses for outreach.',
            'source' => 'patients',
            'format' => 'csv',
            'filters' => [
                'q' => 'aziza',
                'has_email' => true,
                'limit' => 100,
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'report_created')
        ->assertJsonPath('data.code', 'daily_patients')
        ->json('data.id');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/reports?source=patients')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reportId)
        ->assertJsonPath('meta.filters.source', 'patients');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/reports/'.$reportId)
        ->assertOk()
        ->assertJsonPath('data.id', $reportId)
        ->assertJsonPath('data.latest_run', null);

    $runResponse = $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'reports-run-1',
        ])
        ->postJson('/api/v1/reports/'.$reportId.':run')
        ->assertOk()
        ->assertJsonPath('status', 'report_run_completed')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.row_count', 1);

    $path = $runResponse->json('data.storage.path');
    Storage::disk('artifacts')->assertExists($path);

    $downloadResponse = $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->get('/api/v1/reports/'.$reportId.'/download')
        ->assertOk();

    expect($downloadResponse->streamedContent())->toContain('first_name');
    expect($downloadResponse->streamedContent())->toContain('Aziza');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'reports-delete-1',
        ])
        ->deleteJson('/api/v1/reports/'.$reportId)
        ->assertOk()
        ->assertJsonPath('status', 'report_deleted')
        ->assertJsonPath('data.deleted_at', fn (string $deletedAt): bool => $deletedAt !== '');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/reports/'.$reportId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    expect(AuditEventRecord::query()->where('action', 'reports.created')->where('object_id', $reportId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'reports.ran')->where('object_id', $reportId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'reports.deleted')->where('object_id', $reportId)->exists())->toBeTrue();
});
