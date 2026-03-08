<?php

namespace App\Shared\Application\Contracts;

interface CacheKeyBuilder
{
    /**
     * @param  list<int|float|string|bool>  $segments
     */
    public function itemKey(string $domain, array $segments, ?string $tenantId, int $namespaceVersion): string;

    public function namespaceKey(string $domain, ?string $tenantId): string;
}
