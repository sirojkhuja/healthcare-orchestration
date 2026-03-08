<?php

namespace App\Shared\Infrastructure\Tenancy;

use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Exceptions\MissingTenantContext;
use Illuminate\Support\Facades\Context;

final class RequestTenantContext implements TenantContext
{
    private ?string $tenantId = null;

    private ?string $source = null;

    #[\Override]
    public function initialize(?string $tenantId, ?string $source = null): void
    {
        $this->tenantId = $tenantId;
        $this->source = $source;

        if ($tenantId === null) {
            Context::forget([
                'tenant_id',
                'tenant_context_source',
            ]);

            return;
        }

        Context::add([
            'tenant_id' => $tenantId,
            'tenant_context_source' => $source,
        ]);
    }

    #[\Override]
    public function hasTenant(): bool
    {
        return $this->tenantId() !== null;
    }

    #[\Override]
    public function tenantId(): ?string
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        $context = Context::only(['tenant_id']);

        if (! array_key_exists('tenant_id', $context) || ! is_string($context['tenant_id'])) {
            return null;
        }

        return $context['tenant_id'];
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
        if ($this->source !== null) {
            return $this->source;
        }

        $context = Context::only(['tenant_context_source']);

        if (! array_key_exists('tenant_context_source', $context) || ! is_string($context['tenant_context_source'])) {
            return null;
        }

        return $context['tenant_context_source'];
    }

    #[\Override]
    public function clear(): void
    {
        $this->tenantId = null;
        $this->source = null;
        Context::forget([
            'tenant_id',
            'tenant_context_source',
        ]);
    }
}
