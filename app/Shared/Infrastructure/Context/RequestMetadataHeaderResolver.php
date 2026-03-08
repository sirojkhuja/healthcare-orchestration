<?php

namespace App\Shared\Infrastructure\Context;

use LogicException;

final class RequestMetadataHeaderResolver
{
    /**
     * @return array{request_id: non-empty-string, correlation_id: non-empty-string, causation_id: non-empty-string}
     */
    public function resolve(): array
    {
        $headerNames = config('medflow.request_context.headers', []);

        if (! is_array($headerNames)) {
            throw new LogicException('Request metadata header configuration must be an array.');
        }

        $defaults = [
            'request_id' => 'X-Request-Id',
            'correlation_id' => 'X-Correlation-Id',
            'causation_id' => 'X-Causation-Id',
        ];

        $resolved = [];

        foreach ($defaults as $key => $fallback) {
            $value = $headerNames[$key] ?? $fallback;

            if (! is_string($value) || $value === '') {
                throw new LogicException("The configured {$key} header must be a non-empty string.");
            }

            $resolved[$key] = $value;
        }

        /** @var array{request_id: non-empty-string, correlation_id: non-empty-string, causation_id: non-empty-string} $resolved */
        return $resolved;
    }
}
