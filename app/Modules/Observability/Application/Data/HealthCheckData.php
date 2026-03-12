<?php

namespace App\Modules\Observability\Application\Data;

final readonly class HealthCheckData
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $key,
        public string $status,
        public string $message,
        public array $details = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'status' => $this->status,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
