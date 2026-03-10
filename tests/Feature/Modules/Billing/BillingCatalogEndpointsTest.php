<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/BillingTestSupport.php';

uses(RefreshDatabase::class);

it('manages billable services and price lists with default replacement semantics', function (): void {
    $admin = User::factory()->create([
        'email' => 'billing.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'billing.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = billingIssueBearerToken($this, 'billing.admin@openai.com');
    $viewerToken = billingIssueBearerToken($this, 'billing.viewer@openai.com');
    $tenantId = billingCreateTenant($this, $adminToken, 'Billing Tenant')->json('data.id');

    billingGrantPermissions($admin, $tenantId, ['billing.view', 'billing.manage']);
    billingGrantPermissions($viewer, $tenantId, ['billing.view']);

    $consultationId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'consult-initial',
        'name' => 'Initial Consultation',
        'category' => 'consultation',
        'unit' => 'visit',
    ], 'billing-service-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'billable_service_created')
        ->assertJsonPath('data.code', 'CONSULT-INITIAL')
        ->json('data.id');

    $xrayId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'xray-chest',
        'name' => 'Chest X-Ray',
        'category' => 'imaging',
        'unit' => 'study',
    ], 'billing-service-create-2')
        ->assertCreated()
        ->json('data.id');

    $homeVisitId = billingCreateService($this, $adminToken, $tenantId, [
        'code' => 'home-visit',
        'name' => 'Home Visit',
        'category' => 'consultation',
        'unit' => 'visit',
    ], 'billing-service-create-3')
        ->assertCreated()
        ->json('data.id');

    billingUpdateService($this, $adminToken, $tenantId, $xrayId, [
        'is_active' => false,
        'description' => 'Retired imaging service.',
    ], 'billing-service-update-1')
        ->assertOk()
        ->assertJsonPath('status', 'billable_service_updated')
        ->assertJsonPath('data.is_active', false);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/services?q=initial&is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.filters.q', 'initial')
        ->assertJsonPath('data.0.id', $consultationId);

    $springPriceListId = billingCreatePriceList($this, $adminToken, $tenantId, [
        'code' => 'spring-2026',
        'name' => 'Spring 2026 Standard',
        'currency' => 'uzs',
        'is_default' => true,
        'effective_from' => '2026-03-10',
    ], 'billing-price-list-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'price_list_created')
        ->assertJsonPath('data.is_default', true)
        ->json('data.id');

    $summerPriceListId = billingCreatePriceList($this, $adminToken, $tenantId, [
        'code' => 'summer-2026',
        'name' => 'Summer 2026 Premium',
        'currency' => 'UZS',
        'is_default' => true,
        'effective_from' => '2026-04-01',
        'description' => 'Premium default price list.',
    ], 'billing-price-list-create-2')
        ->assertCreated()
        ->assertJsonPath('data.currency', 'UZS')
        ->assertJsonPath('data.is_default', true)
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/price-lists/'.$springPriceListId)
        ->assertOk()
        ->assertJsonPath('data.id', $springPriceListId)
        ->assertJsonPath('data.is_default', false);

    billingUpdatePriceList($this, $adminToken, $tenantId, $summerPriceListId, [
        'description' => 'Premium pricing for summer 2026.',
    ], 'billing-price-list-update-1')
        ->assertOk()
        ->assertJsonPath('status', 'price_list_updated')
        ->assertJsonPath('data.description', 'Premium pricing for summer 2026.');

    billingSetPriceListItems($this, $adminToken, $tenantId, $summerPriceListId, [
        'items' => [
            [
                'service_id' => $consultationId,
                'amount' => '120000',
            ],
            [
                'service_id' => $homeVisitId,
                'amount' => '180000.50',
            ],
        ],
    ], 'billing-price-list-items-1')
        ->assertOk()
        ->assertJsonPath('status', 'price_list_items_replaced')
        ->assertJsonPath('data.item_count', 2)
        ->assertJsonPath('data.items.0.service.id', $homeVisitId)
        ->assertJsonPath('data.items.0.unit_price.amount', '180000.50')
        ->assertJsonPath('data.items.1.service.id', $consultationId)
        ->assertJsonPath('data.items.1.unit_price.amount', '120000.00')
        ->assertJsonPath('data.items.1.unit_price.currency', 'UZS');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/price-lists?active_on=2026-04-10&is_default=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.filters.active_on', '2026-04-10')
        ->assertJsonPath('data.0.id', $summerPriceListId);

    billingDeleteService($this, $adminToken, $tenantId, $consultationId, 'billing-service-delete-conflict')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    billingSetPriceListItems($this, $adminToken, $tenantId, $summerPriceListId, [
        'items' => [],
    ], 'billing-price-list-items-clear')
        ->assertOk()
        ->assertJsonPath('status', 'price_list_items_replaced')
        ->assertJsonPath('data.item_count', 0)
        ->assertJsonCount(0, 'data.items');

    billingDeleteService($this, $adminToken, $tenantId, $consultationId, 'billing-service-delete-1')
        ->assertOk()
        ->assertJsonPath('status', 'billable_service_deleted')
        ->assertJsonPath('data.id', $consultationId);

    billingDeletePriceList($this, $adminToken, $tenantId, $summerPriceListId, 'billing-price-list-delete-1')
        ->assertOk()
        ->assertJsonPath('status', 'price_list_deleted')
        ->assertJsonPath('data.id', $summerPriceListId);

    expect(AuditEventRecord::query()->where('action', 'billable_services.created')->where('object_id', $consultationId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'billable_services.updated')->where('object_id', $xrayId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'billable_services.deleted')->where('object_id', $consultationId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'price_lists.created')->where('object_id', $springPriceListId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'price_lists.updated')->where('object_id', $springPriceListId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'price_lists.updated')->where('object_id', $summerPriceListId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'price_lists.items_replaced')->where('object_id', $summerPriceListId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'price_lists.deleted')->where('object_id', $summerPriceListId)->exists())->toBeTrue();
});

it('enforces billing permissions and pricing validation rules', function (): void {
    $manager = User::factory()->create([
        'email' => 'billing.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'billing.viewer.only@openai.com',
        'password' => 'secret-password',
    ]);
    $manageOnly = User::factory()->create([
        'email' => 'billing.manage.only@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = billingIssueBearerToken($this, 'billing.manager@openai.com');
    $viewerToken = billingIssueBearerToken($this, 'billing.viewer.only@openai.com');
    $manageOnlyToken = billingIssueBearerToken($this, 'billing.manage.only@openai.com');
    $tenantId = billingCreateTenant($this, $managerToken, 'Billing Permission Tenant')->json('data.id');

    billingGrantPermissions($manager, $tenantId, ['billing.view', 'billing.manage']);
    billingGrantPermissions($viewer, $tenantId, ['billing.view']);
    billingGrantPermissions($manageOnly, $tenantId, ['billing.manage']);

    billingCreateService($this, $viewerToken, $tenantId, [
        'code' => 'viewer-service',
        'name' => 'Viewer Service',
    ], 'billing-viewer-create')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($manageOnlyToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/services')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $serviceId = billingCreateService($this, $managerToken, $tenantId, [
        'code' => 'lab-cbc',
        'name' => 'CBC Lab Panel',
        'category' => 'laboratory',
    ], 'billing-manager-service-create')
        ->assertCreated()
        ->json('data.id');

    billingCreateService($this, $managerToken, $tenantId, [
        'code' => 'LAB-CBC',
        'name' => 'Duplicate CBC Lab Panel',
    ], 'billing-duplicate-service')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $priceListId = billingCreatePriceList($this, $managerToken, $tenantId, [
        'code' => 'standard-2026',
        'name' => 'Standard 2026',
        'currency' => 'UZS',
    ], 'billing-price-list-standard')
        ->assertCreated()
        ->json('data.id');

    billingCreatePriceList($this, $managerToken, $tenantId, [
        'code' => 'STANDARD-2026',
        'name' => 'Duplicate Standard 2026',
        'currency' => 'UZS',
    ], 'billing-price-list-duplicate')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    billingUpdatePriceList($this, $managerToken, $tenantId, $priceListId, [
        'effective_from' => '2026-04-10',
        'effective_to' => '2026-04-01',
    ], 'billing-price-list-invalid-window')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    billingSetPriceListItems($this, $managerToken, $tenantId, $priceListId, [
        'items' => [
            [
                'service_id' => $serviceId,
                'amount' => '55000',
            ],
            [
                'service_id' => $serviceId,
                'amount' => '65000',
            ],
        ],
    ], 'billing-price-list-duplicate-items')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    billingSetPriceListItems($this, $managerToken, $tenantId, $priceListId, [
        'items' => [
            [
                'service_id' => '11111111-1111-4111-8111-111111111111',
                'amount' => '55000',
            ],
        ],
    ], 'billing-price-list-missing-service')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
