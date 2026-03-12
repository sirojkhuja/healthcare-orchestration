<?php

namespace App\Modules\Reporting\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ReportData
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<ReportRunData>  $runs
     */
    public function __construct(
        public string $reportId,
        public string $tenantId,
        public string $code,
        public string $name,
        public ?string $description,
        public string $source,
        public string $format,
        public array $filters,
        public ?ReportRunData $latestRun,
        public array $runs,
        public ?CarbonImmutable $deletedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->reportId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'source' => $this->source,
            'format' => $this->format,
            'filters' => $this->filters,
            'latest_run' => $this->latestRun?->toArray(),
            'runs' => array_map(
                static fn (ReportRunData $run): array => $run->toArray(),
                $this->runs,
            ),
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
