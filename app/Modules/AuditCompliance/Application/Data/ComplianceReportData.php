<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ComplianceReportData
{
    /**
     * @param  list<string>  $fieldIds
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public string $reportId,
        public string $tenantId,
        public string $type,
        public string $status,
        public int $requestedFieldCount,
        public int $processedFieldCount,
        public int $skippedFieldCount,
        public array $fieldIds,
        public array $summary,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'report_id' => $this->reportId,
            'tenant_id' => $this->tenantId,
            'type' => $this->type,
            'status' => $this->status,
            'requested_field_count' => $this->requestedFieldCount,
            'processed_field_count' => $this->processedFieldCount,
            'skipped_field_count' => $this->skippedFieldCount,
            'field_ids' => $this->fieldIds,
            'summary' => $this->summary,
            'created_at' => $this->createdAt->toIso8601String(),
            'completed_at' => $this->completedAt?->toIso8601String(),
        ];
    }
}
