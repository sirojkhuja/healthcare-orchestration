<?php

use App\Shared\Infrastructure\Cache\TenantCacheKeyBuilder;

it('builds tenant-prefixed cache keys', function (): void {
    $builder = new TenantCacheKeyBuilder;

    $tenantKey = $builder->itemKey('permissions', ['user-123'], 'tenant-1', 3);
    $globalKey = $builder->itemKey('permissions', ['user-123'], null, 1);

    expect($tenantKey)->toBe('medflow:tenant:tenant-1:permissions:v3:user-123');
    expect($globalKey)->toBe('medflow:tenant:global:permissions:v1:user-123');
    expect($builder->namespaceKey('permissions', 'tenant-1'))->toBe('medflow:tenant:tenant-1:permissions:namespace');
});
