<?php

namespace App\Modules\Reporting\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ReportRunData
{
    public function __construct(
        public string $runId,
        public string $reportId,
        public string $tenantId,
        public string $status,
        public string $format,
        public int $rowCount,
        public string $fileName,
        public string $storageDisk,
        public string $storagePath,
        public CarbonImmutable $generatedAt,
        public CarbonImmutable $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->runId,
            'report_id' => $this->reportId,
            'tenant_id' => $this->tenantId,
            'status' => $this->status,
            'format' => $this->format,
            'row_count' => $this->rowCount,
            'file_name' => $this->fileName,
            'storage' => [
                'disk' => $this->storageDisk,
                'path' => $this->storagePath,
            ],
            'generated_at' => $this->generatedAt->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
        ];
    }
}
