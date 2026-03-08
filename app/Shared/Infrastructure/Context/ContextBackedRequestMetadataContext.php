<?php

namespace App\Shared\Infrastructure\Context;

use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Data\RequestMetadata;
use App\Shared\Application\Exceptions\MissingRequestMetadata;
use Illuminate\Support\Facades\Context;

final class ContextBackedRequestMetadataContext implements RequestMetadataContext
{
    #[\Override]
    public function initialize(RequestMetadata $metadata): void
    {
        Context::add($metadata->toArray());
    }

    #[\Override]
    public function hasCurrent(): bool
    {
        return is_string(Context::get('request_id'))
            && is_string(Context::get('correlation_id'))
            && is_string(Context::get('causation_id'));
    }

    #[\Override]
    public function current(): RequestMetadata
    {
        $requestId = Context::get('request_id');
        $correlationId = Context::get('correlation_id');
        $causationId = Context::get('causation_id');

        if (! is_string($requestId) || ! is_string($correlationId) || ! is_string($causationId)) {
            throw new MissingRequestMetadata('Request metadata is required before continuing.');
        }

        return new RequestMetadata(
            requestId: $requestId,
            correlationId: $correlationId,
            causationId: $causationId,
        );
    }

    #[\Override]
    public function clear(): void
    {
        Context::forget([
            'request_id',
            'correlation_id',
            'causation_id',
        ]);
    }
}
