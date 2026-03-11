<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/InsuranceTestSupport.php';

uses(RefreshDatabase::class);

it('manages claim lifecycle attachments search export and rule enforcement', function (): void {
    Storage::fake('attachments');
    Storage::fake('exports');

    $manager = User::factory()->create([
        'email' => 'claims.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'claims.viewer@openai.com',
        'password' => 'secret-password',
    ]);
    $outsider = User::factory()->create([
        'email' => 'claims.outsider@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = insuranceIssueBearerToken($this, 'claims.manager@openai.com');
    $viewerToken = insuranceIssueBearerToken($this, 'claims.viewer@openai.com');
    $outsiderToken = insuranceIssueBearerToken($this, 'claims.outsider@openai.com');
    $tenantId = insuranceCreateTenant($this, $managerToken, 'Claims Lifecycle Tenant')->json('data.id');

    insuranceGrantPermissions($manager, $tenantId, [
        'claims.view',
        'claims.manage',
        'billing.view',
        'billing.manage',
        'patients.view',
        'patients.manage',
    ]);
    insuranceGrantPermissions($viewer, $tenantId, ['claims.view']);
    billingEnsureMembership($outsider, $tenantId);

    $patientId = insuranceCreatePatient($this, $managerToken, $tenantId, [
        'first_name' => 'Nodira',
        'last_name' => 'Azimova',
    ])->assertCreated()->json('data.id');

    $policyId = insuranceMutatePatientInsurance($this, $managerToken, $tenantId, $patientId, [
        'insurance_code' => 'uhc-ppo',
        'policy_number' => 'POL-001',
        'member_number' => 'MEM-1001',
        'group_number' => 'GRP-55',
        'plan_name' => 'PPO Prime',
        'effective_from' => '2026-03-01',
        'is_primary' => true,
    ])->assertCreated()->json('data.id');

    $serviceId = insuranceCreateService($this, $managerToken, $tenantId, [
        'code' => 'consult-claim',
        'name' => 'Claim Consultation',
        'category' => 'consultation',
        'unit' => 'visit',
    ], 'claims-service-create-1')->assertCreated()->json('data.id');

    $invoiceId = insuranceCreateInvoice($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
        'invoice_date' => '2026-03-11',
    ], 'claims-invoice-create-1')->assertCreated()->json('data.id');

    insuranceAddInvoiceItem($this, $managerToken, $tenantId, $invoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '120000',
    ], 'claims-invoice-item-1')->assertCreated();

    insuranceIssueInvoice($this, $managerToken, $tenantId, $invoiceId, 'claims-invoice-issue-1')->assertOk();

    $payerId = insuranceCreatePayer($this, $managerToken, $tenantId, [
        'code' => 'uhc',
        'name' => 'United Health',
        'insurance_code' => 'uhc-ppo',
    ], 'claims-payer-create-runtime')
        ->assertCreated()
        ->json('data.id');

    insuranceCreateRule($this, $managerToken, $tenantId, [
        'payer_id' => $payerId,
        'code' => 'consult-rule',
        'name' => 'Consult Rule',
        'service_category' => 'consultation',
        'requires_attachment' => true,
        'requires_primary_policy' => true,
        'max_claim_amount' => '150000',
        'submission_window_days' => 30,
    ], 'claims-rule-create-runtime')->assertCreated();

    $claimId = insuranceCreateClaim($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'payer_id' => $payerId,
        'patient_policy_id' => $policyId,
        'billed_amount' => '120000',
        'service_date' => '2026-03-11',
        'notes' => 'Initial claim draft',
    ], 'claims-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'claim_created')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.policy.id', $policyId)
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/claims?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $claimId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/claims/search?q=clm-&payer_id='.$payerId)
        ->assertOk()
        ->assertJsonPath('meta.filters.payer_id', $payerId)
        ->assertJsonPath('data.0.id', $claimId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/claims/'.$claimId)
        ->assertOk()
        ->assertJsonPath('data.id', $claimId)
        ->assertJsonPath('data.amounts.billed.amount', '120000.00');

    insurancePostClaimAction($this, $managerToken, $tenantId, $claimId, 'submit', [], 'claims-submit-without-attachment')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $attachmentId = insuranceUploadClaimAttachment($this, $managerToken, $tenantId, $claimId, [
        'file' => UploadedFile::fake()->create('claim.pdf', 48, 'application/pdf'),
        'attachment_type' => 'invoice_copy',
        'notes' => 'Scanned invoice',
    ], 'claims-attachment-upload-1')
        ->assertCreated()
        ->assertJsonPath('status', 'claim_attachment_uploaded')
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/claims/'.$claimId.'/attachments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $attachmentId);

    insurancePostClaimAction($this, $managerToken, $tenantId, $claimId, 'submit', [], 'claims-submit-1')
        ->assertOk()
        ->assertJsonPath('status', 'claim_submitted')
        ->assertJsonPath('data.status', 'submitted');

    insurancePostClaimAction($this, $managerToken, $tenantId, $claimId, 'start-review', [
        'reason' => 'All documents received.',
        'source_evidence' => 'Attachment review checklist.',
    ], 'claims-start-review-1')
        ->assertOk()
        ->assertJsonPath('status', 'claim_review_started')
        ->assertJsonPath('data.status', 'under_review');

    insurancePostClaimAction($this, $managerToken, $tenantId, $claimId, 'approve', [
        'approved_amount' => '110000',
        'reason' => 'Allowed amount confirmed.',
        'source_evidence' => 'Manual adjudication sheet.',
    ], 'claims-approve-1')
        ->assertOk()
        ->assertJsonPath('status', 'claim_approved')
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.amounts.approved.amount', '110000.00');

    insurancePostClaimAction($this, $managerToken, $tenantId, $claimId, 'mark-paid', [
        'paid_amount' => '110000',
        'reason' => 'Bank transfer settled.',
        'source_evidence' => 'Settlement batch 2026-03-11.',
    ], 'claims-mark-paid-1')
        ->assertOk()
        ->assertJsonPath('status', 'claim_paid')
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.amounts.paid.amount', '110000.00');

    insurancePostClaimAction($this, $managerToken, $tenantId, $claimId, 'reopen', [
        'reason' => 'Appeal received.',
        'source_evidence' => 'Appeal packet reference A-12.',
    ], 'claims-reopen-1')
        ->assertOk()
        ->assertJsonPath('status', 'claim_reopened')
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.amounts.approved', null)
        ->assertJsonPath('data.amounts.paid', null);

    $deniedClaimId = insuranceCreateClaim($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'payer_id' => $payerId,
        'patient_policy_id' => $policyId,
        'billed_amount' => '100000',
    ], 'claims-create-2')->assertCreated()->json('data.id');

    insuranceUploadClaimAttachment($this, $managerToken, $tenantId, $deniedClaimId, [
        'file' => UploadedFile::fake()->create('second.pdf', 24, 'application/pdf'),
    ], 'claims-attachment-upload-2')->assertCreated();

    insurancePostClaimAction($this, $managerToken, $tenantId, $deniedClaimId, 'submit', [], 'claims-submit-2')
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    insurancePostClaimAction($this, $managerToken, $tenantId, $deniedClaimId, 'start-review', [
        'reason' => 'Secondary review.',
        'source_evidence' => 'Queue assignment.',
    ], 'claims-start-review-2')
        ->assertOk();

    insurancePostClaimAction($this, $managerToken, $tenantId, $deniedClaimId, 'deny', [
        'reason' => 'Coverage excludes this service.',
        'source_evidence' => 'Policy exclusion page.',
    ], 'claims-deny-2')
        ->assertOk()
        ->assertJsonPath('status', 'claim_denied')
        ->assertJsonPath('data.status', 'denied')
        ->assertJsonPath('data.denial_reason', 'Coverage excludes this service.');

    $draftDeleteId = insuranceCreateClaim($this, $managerToken, $tenantId, [
        'invoice_id' => $invoiceId,
        'payer_id' => $payerId,
        'patient_policy_id' => $policyId,
        'billed_amount' => '90000',
    ], 'claims-create-3')->assertCreated()->json('data.id');

    insuranceDeleteClaim($this, $managerToken, $tenantId, $draftDeleteId, 'claims-delete-1')
        ->assertOk()
        ->assertJsonPath('status', 'claim_deleted')
        ->assertJsonPath('data.deleted_at', fn (string $deletedAt): bool => $deletedAt !== '');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'claims-payer-delete-blocked',
        ])
        ->deleteJson('/api/v1/insurance/payers/'.$payerId)
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($managerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/claims/export?format=csv')
        ->assertOk()
        ->assertJsonPath('status', 'claim_export_created')
        ->assertJsonPath('data.row_count', 2);

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'claims-viewer-create',
        ])
        ->postJson('/api/v1/claims', [
            'invoice_id' => $invoiceId,
            'payer_id' => $payerId,
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($outsiderToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/claims')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    expect(AuditEventRecord::query()->where('action', 'claims.created')->count())->toBe(3);
    expect(AuditEventRecord::query()->where('action', 'claims.submitted')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'claims.review_started')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'claims.approved')->where('object_id', $claimId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'claims.paid')->where('object_id', $claimId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'claims.reopened')->where('object_id', $claimId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'claims.denied')->where('object_id', $deniedClaimId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'claims.deleted')->where('object_id', $draftDeleteId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'claim_attachments.uploaded')->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'claims.exported')->exists())->toBeTrue();
});
