<?php

namespace App\Shared\Application\Data;

final readonly class ApiError
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public array $details,
        public string $traceId,
        public string $correlationId,
    ) {}

    /**
     * @return array{code: string, message: string, details: array<string, mixed>, trace_id: string, correlation_id: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'details' => $this->details,
            'trace_id' => $this->traceId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
