<?php

namespace App\Shared\Application\Contracts;

use Closure;
use DateInterval;
use DateTimeInterface;

interface TenantCache
{
    /**
     * @param  list<int|float|string|bool>  $segments
     */
    public function forget(string $domain, array $segments, ?string $tenantId): void;

    public function invalidate(string $domain, ?string $tenantId): void;

    /**
     * @template TValue
     *
     * @param  list<int|float|string|bool>  $segments
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $domain, array $segments, ?string $tenantId, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed;
}
