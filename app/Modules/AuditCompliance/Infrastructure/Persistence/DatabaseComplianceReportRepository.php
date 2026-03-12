<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Modules\AuditCompliance\Application\Contracts\ComplianceReportRepository;
use App\Modules\AuditCompliance\Application\Data\ComplianceReportData;
use App\Modules\AuditCompliance\Application\Data\ComplianceReportSearchCriteria;
use Carbon\CarbonImmutable;

final class DatabaseComplianceReportRepository implements ComplianceReportRepository
{
    #[\Override]
    public function append(ComplianceReportData $report): void
    {
        ComplianceReportRecord::query()->create([
            'id' => $report->reportId,
            'tenant_id' => $report->tenantId,
            'type' => $report->type,
            'status' => $report->status,
            'requested_field_count' => $report->requestedFieldCount,
            'processed_field_count' => $report->processedFieldCount,
            'skipped_field_count' => $report->skippedFieldCount,
            'field_ids' => $report->fieldIds,
            'summary' => $report->summary,
            'completed_at' => $report->completedAt,
            'created_at' => $report->createdAt,
            'updated_at' => $report->createdAt,
        ]);
    }

    #[\Override]
    public function listForTenant(string $tenantId, ComplianceReportSearchCriteria $criteria): array
    {
        $query = ComplianceReportRecord::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($criteria->limit);

        if ($criteria->type !== null) {
            $query->where('type', $criteria->type);
        }

        if ($criteria->status !== null) {
            $query->where('status', $criteria->status);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, ComplianceReportRecord> $records */
        $records = $query->get();

        /** @var list<ComplianceReportData> $reports */
        $reports = array_values(array_map(
            fn (ComplianceReportRecord $record) => $this->toData($record),
            $records->all(),
        ));

        return $reports;
    }

    private function toData(ComplianceReportRecord $record): ComplianceReportData
    {
        return new ComplianceReportData(
            reportId: $this->stringValue($record->getAttribute('id')),
            tenantId: $this->stringValue($record->getAttribute('tenant_id')),
            type: $this->stringValue($record->getAttribute('type')),
            status: $this->stringValue($record->getAttribute('status')),
            requestedFieldCount: $this->intValue($record->getAttribute('requested_field_count')),
            processedFieldCount: $this->intValue($record->getAttribute('processed_field_count')),
            skippedFieldCount: $this->intValue($record->getAttribute('skipped_field_count')),
            fieldIds: $this->stringListValue($record->getAttribute('field_ids')),
            summary: $this->arrayValue($record->getAttribute('summary')),
            createdAt: CarbonImmutable::parse($this->stringValue($record->getAttribute('created_at'))),
            completedAt: $record->getAttribute('completed_at') !== null
                ? CarbonImmutable::parse($this->stringValue($record->getAttribute('completed_at')))
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @return list<string>
     */
    private function stringListValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
