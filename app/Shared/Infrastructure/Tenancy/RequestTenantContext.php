<?php

namespace App\Shared\Infrastructure\Tenancy;

use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Exceptions\MissingTenantContext;

final class RequestTenantContext implements TenantContext
{
    private ?string $tenantId = null;

    private ?string $source = null;

    #[\Override]
    public function initialize(?string $tenantId, ?string $source = null): void
    {
        $this->tenantId = $tenantId;
        $this->source = $source;
    }

    #[\Override]
    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    #[\Override]
    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    #[\Override]
    public function requireTenantId(): string
    {
        if ($this->tenantId === null) {
            throw new MissingTenantContext('Tenant context is required for this request.');
        }

        return $this->tenantId;
    }

    #[\Override]
    public function source(): ?string
    {
        return $this->source;
    }

    #[\Override]
    public function clear(): void
    {
        $this->tenantId = null;
        $this->source = null;
    }
}
