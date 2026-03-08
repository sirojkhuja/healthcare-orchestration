<?php

namespace App\Shared\Infrastructure\Context;

use App\Shared\Application\Contracts\EventContextFactory;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;

final class StandardEventContextFactory implements EventContextFactory
{
    public function __construct(
        private readonly RequestMetadataContext $requestMetadataContext,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{request_id: string, correlation_id: string, causation_id: string, tenant_id?: string}
     */
    #[\Override]
    public function make(?string $causationId = null): array
    {
        $requestMetadata = $this->requestMetadataContext->current();
        $eventContext = [
            'request_id' => $requestMetadata->requestId,
            'correlation_id' => $requestMetadata->correlationId,
            'causation_id' => $causationId ?? $requestMetadata->requestId,
        ];

        $tenantId = $this->tenantContext->tenantId();

        if ($tenantId !== null) {
            $eventContext['tenant_id'] = $tenantId;
        }

        return $eventContext;
    }
}
