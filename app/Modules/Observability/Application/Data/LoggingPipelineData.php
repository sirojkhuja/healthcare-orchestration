<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class LoggingPipelineData
{
    public function __construct(
        public string $key,
        public string $name,
        public string $destination,
        public bool $enabled,
        public string $status,
        public ?CarbonImmutable $lastReloadedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'destination' => $this->destination,
            'enabled' => $this->enabled,
            'status' => $this->status,
            'last_reloaded_at' => $this->lastReloadedAt?->toIso8601String(),
        ];
    }
}
