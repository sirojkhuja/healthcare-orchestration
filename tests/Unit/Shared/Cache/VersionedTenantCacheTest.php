<?php

use App\Shared\Application\Contracts\TenantCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::store()->flush();
});

it('isolates cache entries by tenant scope', function (): void {
    $tenantCache = app(TenantCache::class);
    $loadCount = 0;

    $firstTenantValue = $tenantCache->remember('settings', ['dashboard'], 'tenant-a', null, function () use (&$loadCount): string {
        $loadCount++;

        return 'tenant-a-value';
    });

    $repeatFirstTenantValue = $tenantCache->remember('settings', ['dashboard'], 'tenant-a', null, function () use (&$loadCount): string {
        $loadCount++;

        return 'unexpected';
    });

    $secondTenantValue = $tenantCache->remember('settings', ['dashboard'], 'tenant-b', null, function () use (&$loadCount): string {
        $loadCount++;

        return 'tenant-b-value';
    });

    expect($firstTenantValue)->toBe('tenant-a-value');
    expect($repeatFirstTenantValue)->toBe('tenant-a-value');
    expect($secondTenantValue)->toBe('tenant-b-value');
    expect($loadCount)->toBe(2);
});

it('invalidates a tenant cache namespace explicitly', function (): void {
    $tenantCache = app(TenantCache::class);
    $loadCount = 0;

    $initial = $tenantCache->remember('permissions', ['user-123'], 'tenant-a', null, function () use (&$loadCount): string {
        $loadCount++;

        return 'initial';
    });

    $tenantCache->invalidate('permissions', 'tenant-a');

    $reloaded = $tenantCache->remember('permissions', ['user-123'], 'tenant-a', null, function () use (&$loadCount): string {
        $loadCount++;

        return 'reloaded';
    });

    expect($initial)->toBe('initial');
    expect($reloaded)->toBe('reloaded');
    expect($loadCount)->toBe(2);
});

it('forgets a single cache entry without clearing sibling keys', function (): void {
    $tenantCache = app(TenantCache::class);
    $alphaLoads = 0;
    $betaLoads = 0;

    $tenantCache->remember('availability', ['alpha'], 'tenant-a', null, function () use (&$alphaLoads): string {
        $alphaLoads++;

        return 'alpha-1';
    });

    $tenantCache->remember('availability', ['beta'], 'tenant-a', null, function () use (&$betaLoads): string {
        $betaLoads++;

        return 'beta-1';
    });

    $tenantCache->forget('availability', ['alpha'], 'tenant-a');

    $alphaReloaded = $tenantCache->remember('availability', ['alpha'], 'tenant-a', null, function () use (&$alphaLoads): string {
        $alphaLoads++;

        return 'alpha-2';
    });

    $betaStillCached = $tenantCache->remember('availability', ['beta'], 'tenant-a', null, function () use (&$betaLoads): string {
        $betaLoads++;

        return 'unexpected';
    });

    expect($alphaReloaded)->toBe('alpha-2');
    expect($betaStillCached)->toBe('beta-1');
    expect($alphaLoads)->toBe(2);
    expect($betaLoads)->toBe(1);
});
