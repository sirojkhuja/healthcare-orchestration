<?php

namespace App\Shared\Application\Contracts;

interface TenantContext
{
    public function initialize(?string $tenantId, ?string $source = null): void;

    public function hasTenant(): bool;

    public function tenantId(): ?string;

    public function requireTenantId(): string;

    public function source(): ?string;

    public function clear(): void;
}
