<?php

namespace App\Shared\Application\Data;

final readonly class RequestMetadata
{
    public function __construct(
        public string $requestId,
        public string $correlationId,
        public string $causationId,
    ) {}

    /**
     * @return array{request_id: string, correlation_id: string, causation_id: string}
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
        ];
    }
}
