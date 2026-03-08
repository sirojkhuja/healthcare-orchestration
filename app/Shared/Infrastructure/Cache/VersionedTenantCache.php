<?php

namespace App\Shared\Infrastructure\Cache;

use App\Shared\Application\Contracts\CacheKeyBuilder;
use App\Shared\Application\Contracts\TenantCache;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

final class VersionedTenantCache implements TenantCache
{
    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private readonly CacheKeyBuilder $cacheKeyBuilder,
    ) {}

    #[\Override]
    public function forget(string $domain, array $segments, ?string $tenantId): void
    {
        $this->cacheFactory->store()->forget(
            $this->cacheKeyBuilder->itemKey($domain, $segments, $tenantId, $this->namespaceVersion($domain, $tenantId)),
        );
    }

    #[\Override]
    public function invalidate(string $domain, ?string $tenantId): void
    {
        $namespaceKey = $this->cacheKeyBuilder->namespaceKey($domain, $tenantId);
        $store = $this->cacheFactory->store();
        $nextVersion = $this->namespaceVersion($domain, $tenantId) + 1;

        $store->forever($namespaceKey, $nextVersion);
    }

    #[\Override]
    public function remember(string $domain, array $segments, ?string $tenantId, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        $store = $this->cacheFactory->store();
        $key = $this->cacheKeyBuilder->itemKey($domain, $segments, $tenantId, $this->namespaceVersion($domain, $tenantId));

        if ($ttl === null) {
            return $store->rememberForever($key, $callback);
        }

        return $store->remember($key, $ttl, $callback);
    }

    private function namespaceVersion(string $domain, ?string $tenantId): int
    {
        $store = $this->cacheFactory->store();
        $namespaceKey = $this->cacheKeyBuilder->namespaceKey($domain, $tenantId);
        /** @var mixed $value */
        $value = $store->get($namespaceKey);

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $store->forever($namespaceKey, 1);

        return 1;
    }
}
