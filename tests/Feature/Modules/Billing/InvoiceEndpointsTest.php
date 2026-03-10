<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/BillingTestSupport.php';

uses(RefreshDatabase::class);

it('manages invoice crud item pricing search export and lifecycle transitions', function (): void {
    $admin = User::factory()->create([
        'email' => 'invoice.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'invoice.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = billingIssueBearerToken($this, 'invoice.admin@openai.com');
    $viewerToken = billingIssueBearerToken($this, 'invoice.viewer@openai.com');
    $tenantId = billingCreateTenant($this, $adminToken, 'Invoice Tenant')->json('data.id');

    billingGrantPermissions($admin, $tenantId, ['billing.view', 'billing.manage', 'patients.manage']);
    billingGrantPermissions($viewer, $tenantId, ['billing.view']);

    $patientId = billingCreatePatient($this, $adminToken, $tenantId, [
        'first_name' => 'Aisha',
        'last_name' => 'Karimova',
    ])->assertCreated()->json('data.id');

    $consultationId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'consult-follow-up',
        'name' => 'Follow-up Consultation',
        'category' => 'consultation',
        'unit' => 'visit',
    ], 'invoice-service-create-1')->assertCreated()->json('data.id');

    $labId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'cbc-panel',
        'name' => 'CBC Panel',
        'category' => 'laboratory',
        'unit' => 'panel',
    ], 'invoice-service-create-2')->assertCreated()->json('data.id');

    $otherServiceId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'admin-fee',
        'name' => 'Administrative Fee',
        'category' => 'fees',
        'unit' => 'item',
    ], 'invoice-service-create-3')->assertCreated()->json('data.id');

    $priceListId = billingCreatePriceList($this, $adminToken, $tenantId, [
        'code' => 'standard-billing',
        'name' => 'Standard Billing',
        'currency' => 'UZS',
        'is_default' => true,
    ], 'invoice-price-list-create-1')->assertCreated()->json('data.id');

    billingSetPriceListItems($this, $adminToken, $tenantId, $priceListId, [
        'items' => [
            ['service_id' => $consultationId, 'amount' => '120000'],
            ['service_id' => $labId, 'amount' => '90000'],
        ],
    ], 'invoice-price-list-items-1')
        ->assertOk()
        ->assertJsonPath('data.item_count', 2);

    $invoiceId = billingCreateInvoice($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'price_list_id' => $priceListId,
        'invoice_date' => '2026-03-11',
        'due_on' => '2026-03-18',
        'notes' => 'Collect at reception.',
    ], 'invoice-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'invoice_created')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.currency', 'UZS')
        ->assertJsonPath('data.patient.id', $patientId)
        ->assertJsonPath('data.price_list.id', $priceListId)
        ->assertJsonPath('data.item_count', 0)
        ->json('data.id');

    billingAddInvoiceItem($this, $adminToken, $tenantId, $invoiceId, [
        'service_id' => $consultationId,
        'quantity' => '1',
    ], 'invoice-item-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'invoice_item_created')
        ->assertJsonPath('data.item_count', 1)
        ->assertJsonPath('data.totals.total.amount', '120000.00')
        ->assertJsonPath('data.items.0.service.id', $consultationId)
        ->assertJsonPath('data.items.0.unit_price.amount', '120000.00');

    $invoiceAfterSecondItem = billingAddInvoiceItem($this, $adminToken, $tenantId, $invoiceId, [
        'service_id' => $otherServiceId,
        'description' => 'Front desk processing fee',
        'quantity' => '2',
        'unit_price_amount' => '25000',
    ], 'invoice-item-create-2')
        ->assertCreated()
        ->assertJsonPath('data.item_count', 2)
        ->assertJsonPath('data.totals.total.amount', '170000.00')
        ->json('data');

    $feeItemId = collect($invoiceAfterSecondItem['items'])
        ->firstWhere('service.id', $otherServiceId)['id'];

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/invoices')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $invoiceId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/invoices/'.$invoiceId.'/items')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/invoices/search?q=inv-&status=draft')
        ->assertOk()
        ->assertJsonPath('meta.filters.q', 'inv-')
        ->assertJsonPath('meta.filters.status', 'draft')
        ->assertJsonPath('data.0.id', $invoiceId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/invoices/export?format=csv')
        ->assertOk()
        ->assertJsonPath('status', 'invoice_export_created')
        ->assertJsonPath('data.row_count', 1)
        ->assertJsonPath('data.format', 'csv');

    billingUpdateInvoice($this, $adminToken, $tenantId, $invoiceId, [
        'notes' => 'Collect at cashier desk.',
    ], 'invoice-update-1')
        ->assertOk()
        ->assertJsonPath('status', 'invoice_updated')
        ->assertJsonPath('data.notes', 'Collect at cashier desk.');

    billingUpdateInvoiceItem($this, $adminToken, $tenantId, $invoiceId, $feeItemId, [
        'quantity' => '3',
    ], 'invoice-item-update-1')
        ->assertOk()
        ->assertJsonPath('status', 'invoice_item_updated')
        ->assertJsonPath('data.totals.total.amount', '195000.00');

    billingDeleteInvoiceItem($this, $adminToken, $tenantId, $invoiceId, $feeItemId, 'invoice-item-delete-1')
        ->assertOk()
        ->assertJsonPath('status', 'invoice_item_deleted')
        ->assertJsonPath('data.item_count', 1)
        ->assertJsonPath('data.totals.total.amount', '120000.00');

    billingIssueInvoice($this, $adminToken, $tenantId, $invoiceId, 'invoice-issue-1')
        ->assertOk()
        ->assertJsonPath('status', 'invoice_issued')
        ->assertJsonPath('data.status', 'issued');

    billingUpdateInvoice($this, $adminToken, $tenantId, $invoiceId, [
        'notes' => 'Should fail after issue',
    ], 'invoice-update-after-issue')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    billingAddInvoiceItem($this, $adminToken, $tenantId, $invoiceId, [
        'service_id' => $labId,
        'quantity' => '1',
    ], 'invoice-item-after-issue')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    billingFinalizeInvoice($this, $adminToken, $tenantId, $invoiceId, 'invoice-finalize-1')
        ->assertOk()
        ->assertJsonPath('status', 'invoice_finalized')
        ->assertJsonPath('data.status', 'finalized');

    billingVoidInvoice($this, $adminToken, $tenantId, $invoiceId, [
        'reason' => 'Issued under the wrong patient account.',
    ], 'invoice-void-1')
        ->assertOk()
        ->assertJsonPath('status', 'invoice_voided')
        ->assertJsonPath('data.status', 'void');

    billingDeleteInvoice($this, $adminToken, $tenantId, $invoiceId, 'invoice-delete-1')
        ->assertOk()
        ->assertJsonPath('status', 'invoice_deleted')
        ->assertJsonPath('data.deleted_at', fn (string $deletedAt): bool => $deletedAt !== '');

    expect(AuditEventRecord::query()->where('action', 'invoices.created')->where('object_id', $invoiceId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'invoice_items.created')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'invoice_items.updated')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'invoice_items.deleted')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'invoices.exported')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'invoices.issued')->where('object_id', $invoiceId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'invoices.finalized')->where('object_id', $invoiceId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'invoices.voided')->where('object_id', $invoiceId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'invoices.deleted')->where('object_id', $invoiceId)->exists())->toBeTrue();
});

it('enforces invoice permissions pricing and lifecycle validation rules', function (): void {
    $manager = User::factory()->create([
        'email' => 'invoice.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'invoice.viewer.only@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = billingIssueBearerToken($this, 'invoice.manager@openai.com');
    $viewerToken = billingIssueBearerToken($this, 'invoice.viewer.only@openai.com');
    $tenantId = billingCreateTenant($this, $managerToken, 'Invoice Validation Tenant')->json('data.id');

    billingGrantPermissions($manager, $tenantId, ['billing.view', 'billing.manage', 'patients.manage']);
    billingGrantPermissions($viewer, $tenantId, ['billing.view']);

    $patientId = billingCreatePatient($this, $managerToken, $tenantId)->assertCreated()->json('data.id');
    $serviceId = billingCreateService($this, $managerToken, $tenantId, [
        'code' => 'inactive-service',
        'name' => 'Inactive service',
        'is_active' => false,
    ], 'invoice-validation-service')
        ->assertCreated()
        ->json('data.id');

    billingCreateInvoice($this, $viewerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
    ], 'invoice-viewer-create')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    billingCreateInvoice($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
    ], 'invoice-missing-currency')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    billingCreateInvoice($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
        'invoice_date' => '2026-03-11',
        'due_on' => '2026-03-10',
    ], 'invoice-invalid-due-date')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $draftInvoiceId = billingCreateInvoice($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'currency' => 'UZS',
    ], 'invoice-draft-create')
        ->assertCreated()
        ->json('data.id');

    billingIssueInvoice($this, $managerToken, $tenantId, $draftInvoiceId, 'invoice-empty-issue')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    billingFinalizeInvoice($this, $managerToken, $tenantId, $draftInvoiceId, 'invoice-finalize-before-issue')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    billingAddInvoiceItem($this, $managerToken, $tenantId, $draftInvoiceId, [
        'service_id' => $serviceId,
        'quantity' => '1',
        'unit_price_amount' => '5000',
    ], 'invoice-inactive-service-item')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    billingAddInvoiceItem($this, $managerToken, $tenantId, $draftInvoiceId, [
        'service_id' => '11111111-1111-4111-8111-111111111111',
        'quantity' => '1',
    ], 'invoice-missing-price-and-service')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $pricedServiceId = billingCreateService($this, $managerToken, $tenantId, [
        'code' => 'visit-basic',
        'name' => 'Basic Visit',
    ], 'invoice-validation-service-2')->assertCreated()->json('data.id');
    $priceListId = billingCreatePriceList($this, $managerToken, $tenantId, [
        'code' => 'invoice-validation-pl',
        'name' => 'Invoice Validation Price List',
        'currency' => 'UZS',
    ], 'invoice-validation-pl-create')->assertCreated()->json('data.id');
    billingSetPriceListItems($this, $managerToken, $tenantId, $priceListId, [
        'items' => [
            ['service_id' => $pricedServiceId, 'amount' => '80000'],
        ],
    ], 'invoice-validation-pl-items')->assertOk();

    $pricedInvoiceId = billingCreateInvoice($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'price_list_id' => $priceListId,
    ], 'invoice-priced-create')
        ->assertCreated()
        ->json('data.id');

    billingAddInvoiceItem($this, $managerToken, $tenantId, $pricedInvoiceId, [
        'service_id' => $pricedServiceId,
        'quantity' => '1',
    ], 'invoice-priced-item-create')
        ->assertCreated()
        ->assertJsonPath('data.totals.total.amount', '80000.00');

    billingUpdateInvoice($this, $managerToken, $tenantId, $pricedInvoiceId, [
        'currency' => 'USD',
    ], 'invoice-priced-currency-conflict')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    billingVoidInvoice($this, $managerToken, $tenantId, $pricedInvoiceId, [
        'reason' => '',
    ], 'invoice-void-empty-reason')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');
});
