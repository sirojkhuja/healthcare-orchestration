<?php

use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Data\RequestMetadata;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Fixtures\Jobs\StoreRequestMetadataProbeJob;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::dropIfExists('request_metadata_probes');
    Schema::create('request_metadata_probes', function (Blueprint $table): void {
        $table->uuid('probe_id')->primary();
        $table->uuid('request_id');
        $table->uuid('correlation_id');
        $table->uuid('causation_id');
        $table->uuid('tenant_id')->nullable();
    });

    config()->set('queue.default', 'database');
});

it('propagates request metadata and tenant context through queued jobs', function (): void {
    $probeId = (string) Str::uuid();
    $tenantId = (string) Str::uuid();
    $requestId = (string) Str::uuid();
    $correlationId = (string) Str::uuid();
    $causationId = (string) Str::uuid();

    app(RequestMetadataContext::class)->initialize(new RequestMetadata(
        requestId: $requestId,
        correlationId: $correlationId,
        causationId: $causationId,
    ));
    app(TenantContext::class)->initialize($tenantId, 'test');

    dispatch(new StoreRequestMetadataProbeJob($probeId));

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['illuminate:log:context']['data'] ?? null)->toBeArray();

    app(RequestMetadataContext::class)->clear();
    app(TenantContext::class)->clear();

    Artisan::call('queue:work', [
        '--once' => true,
        '--queue' => 'default',
    ]);

    $probe = DB::table('request_metadata_probes')->where('probe_id', $probeId)->first();

    expect($probe)->not->toBeNull();
    expect($probe->request_id)->toBe($requestId);
    expect($probe->correlation_id)->toBe($correlationId);
    expect($probe->causation_id)->toBe($causationId);
    expect($probe->tenant_id)->toBe($tenantId);
});
