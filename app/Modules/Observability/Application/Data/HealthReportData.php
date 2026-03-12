<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class HealthReportData
{
    /**
     * @param  list<HealthCheckData>  $checks
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public string $status,
        public CarbonImmutable $checkedAt,
        public array $checks,
        public array $summary = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'checks' => array_map(
                static fn (HealthCheckData $check): array => $check->toArray(),
                $this->checks,
            ),
            'summary' => $this->summary,
        ];
    }
}
