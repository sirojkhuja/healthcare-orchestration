<?php

use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Fixtures\Models\TenantScopedRecord;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::dropIfExists('tenant_scoped_records');
    Schema::create('tenant_scoped_records', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('tenant_id')->index();
        $table->string('name');
    });

    Route::middleware(['api', 'tenant.require'])->group(function (): void {
        Route::get('/api/v1/_tests/tenant-records', function (TenantContext $tenantContext) {
            return response()->json([
                'tenant_id' => $tenantContext->requireTenantId(),
                'records' => TenantScopedRecord::query()->orderBy('name')->pluck('name')->all(),
            ]);
        });

        Route::get('/api/v1/_tests/tenants/{tenantId}/tenant-records', function (TenantContext $tenantContext) {
            return response()->json([
                'tenant_id' => $tenantContext->requireTenantId(),
                'records' => TenantScopedRecord::query()->orderBy('name')->pluck('name')->all(),
            ]);
        });
    });
});

it('returns only records for the active tenant context', function (): void {
    $tenantAlpha = (string) Str::uuid();
    $tenantBeta = (string) Str::uuid();

    TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenantAlpha,
        'name' => 'Alpha One',
    ]);

    TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenantBeta,
        'name' => 'Beta One',
    ]);

    TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenantAlpha,
        'name' => 'Alpha Two',
    ]);

    $response = $this->withHeader('X-Tenant-Id', $tenantAlpha)
        ->getJson('/api/v1/_tests/tenant-records');

    $response
        ->assertOk()
        ->assertJsonPath('tenant_id', Str::lower($tenantAlpha))
        ->assertJsonPath('records', ['Alpha One', 'Alpha Two']);
});

it('accepts tenant context from the route when the header is absent', function (): void {
    $tenantAlpha = (string) Str::uuid();
    $tenantBeta = (string) Str::uuid();

    TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenantAlpha,
        'name' => 'Alpha One',
    ]);

    TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenantBeta,
        'name' => 'Beta One',
    ]);

    $response = $this->getJson("/api/v1/_tests/tenants/{$tenantBeta}/tenant-records");

    $response
        ->assertOk()
        ->assertJsonPath('tenant_id', Str::lower($tenantBeta))
        ->assertJsonPath('records', ['Beta One']);
});

it('rejects tenant-protected requests when tenant context is missing', function (): void {
    $response = $this->getJson('/api/v1/_tests/tenant-records');

    $response
        ->assertStatus(400)
        ->assertJsonPath('message', 'Tenant context is required for this request.');
});

it('rejects conflicting tenant route and header context', function (): void {
    $tenantAlpha = (string) Str::uuid();
    $tenantBeta = (string) Str::uuid();

    $response = $this->withHeader('X-Tenant-Id', $tenantAlpha)
        ->getJson("/api/v1/_tests/tenants/{$tenantBeta}/tenant-records");

    $response
        ->assertStatus(403)
        ->assertJsonPath('message', 'Tenant route scope does not match the request tenant context.');
});
