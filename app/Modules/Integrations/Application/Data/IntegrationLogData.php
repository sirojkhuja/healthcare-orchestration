<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class IntegrationLogData
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $id,
        public string $integrationKey,
        public string $level,
        public string $event,
        public string $message,
        public array $context,
        public CarbonImmutable $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'integration_key' => $this->integrationKey,
            'level' => $this->level,
            'event' => $this->event,
            'message' => $this->message,
            'context' => $this->context,
            'created_at' => $this->createdAt->toIso8601String(),
        ];
    }
}
