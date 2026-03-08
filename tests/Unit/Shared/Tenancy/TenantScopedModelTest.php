<?php

use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Exceptions\MissingTenantContext;
use App\Shared\Application\Exceptions\TenantScopeViolation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});

test('tenant-owned records inherit tenant id from the active context', function (): void {
    $tenantId = (string) Str::uuid();

    app(TenantContext::class)->initialize($tenantId, 'test');

    $record = TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'name' => 'Alpha One',
    ]);

    expect($record->tenant_id)->toBe($tenantId);
});

test('tenant-owned records reject writes without tenant context', function (): void {
    expect(fn () => TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'name' => 'Alpha One',
    ]))->toThrow(MissingTenantContext::class);
});

test('tenant-owned records reject writes for a different tenant', function (): void {
    $activeTenantId = (string) Str::uuid();
    $otherTenantId = (string) Str::uuid();

    app(TenantContext::class)->initialize($activeTenantId, 'test');

    expect(fn () => TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $otherTenantId,
        'name' => 'Beta One',
    ]))->toThrow(TenantScopeViolation::class);
});

test('tenant-owned records reject invalid tenant identifiers', function (): void {
    expect(fn () => TenantScopedRecord::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => 'invalid-tenant',
        'name' => 'Broken Record',
    ]))->toThrow(TenantScopeViolation::class);
});
