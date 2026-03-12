<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class IntegrationHealthData
{
    /**
     * @param  list<array<string, string|null>>  $checks
     */
    public function __construct(
        public string $integrationKey,
        public string $status,
        public bool $enabled,
        public bool $available,
        public ?string $lastTestStatus,
        public ?CarbonImmutable $lastTestedAt,
        public array $checks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'integration_key' => $this->integrationKey,
            'status' => $this->status,
            'enabled' => $this->enabled,
            'available' => $this->available,
            'last_test_status' => $this->lastTestStatus,
            'last_tested_at' => $this->lastTestedAt?->toIso8601String(),
            'checks' => $this->checks,
        ];
    }
}
