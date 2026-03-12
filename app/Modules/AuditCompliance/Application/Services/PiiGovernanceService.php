<?php

namespace App\Modules\AuditCompliance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Contracts\ComplianceReportRepository;
use App\Modules\AuditCompliance\Application\Contracts\PiiFieldRepository;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\AuditCompliance\Application\Data\ComplianceReportData;
use App\Modules\AuditCompliance\Application\Data\ComplianceReportSearchCriteria;
use App\Modules\AuditCompliance\Application\Data\PiiFieldData;
use App\Modules\AuditCompliance\Application\Data\PiiFieldMutationData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PiiGovernanceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PiiFieldRepository $piiFieldRepository,
        private readonly ComplianceReportRepository $complianceReportRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<PiiFieldData>
     */
    public function listFields(): array
    {
        return $this->piiFieldRepository->listForTenant($this->tenantContext->requireTenantId());
    }

    /**
     * @param  list<PiiFieldMutationData>  $fields
     * @return list<PiiFieldData>
     */
    public function replaceFields(array $fields): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = array_map(
            static fn (PiiFieldData $field): array => $field->toArray(),
            $this->listFields(),
        );
        $afterFields = $this->piiFieldRepository->replace($tenantId, $fields, CarbonImmutable::now());

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'compliance.pii_fields_replaced',
            objectType: 'pii_registry',
            objectId: $tenantId,
            before: ['fields' => $before],
            after: [
                'fields' => array_map(
                    static fn (PiiFieldData $field): array => $field->toArray(),
                    $afterFields,
                ),
            ],
            metadata: [
                'field_count' => count($afterFields),
            ],
        ));

        return $afterFields;
    }

    /**
     * @param  list<string>  $fieldIds
     */
    public function reEncrypt(array $fieldIds): ComplianceReportData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalizedFieldIds = $this->normalizeFieldIds($fieldIds);
        $targetFields = $this->resolveTargets($tenantId, $normalizedFieldIds);
        $now = CarbonImmutable::now();
        $updatedFields = $this->piiFieldRepository->markReencrypted($tenantId, $normalizedFieldIds, $now);
        $report = $this->buildReport('pii_reencryption', $tenantId, $targetFields, $updatedFields, $now, true);
        $this->complianceReportRepository->append($report);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'compliance.pii_fields_reencrypted',
            objectType: 'compliance_report',
            objectId: $report->reportId,
            after: $report->toArray(),
            metadata: [
                'field_ids' => $report->fieldIds,
                'registry_only' => true,
            ],
        ));

        return $report;
    }

    /**
     * @return list<ComplianceReportData>
     */
    public function reports(ComplianceReportSearchCriteria $criteria): array
    {
        return $this->complianceReportRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @param  list<string>  $fieldIds
     */
    public function rotateKeys(array $fieldIds): ComplianceReportData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalizedFieldIds = $this->normalizeFieldIds($fieldIds);
        $targetFields = $this->resolveTargets($tenantId, $normalizedFieldIds);
        $now = CarbonImmutable::now();
        $updatedFields = $this->piiFieldRepository->rotateKeys($tenantId, $normalizedFieldIds, $now);
        $report = $this->buildReport('pii_key_rotation', $tenantId, $targetFields, $updatedFields, $now, false);
        $this->complianceReportRepository->append($report);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'compliance.pii_keys_rotated',
            objectType: 'compliance_report',
            objectId: $report->reportId,
            after: $report->toArray(),
            metadata: [
                'field_ids' => $report->fieldIds,
            ],
        ));

        return $report;
    }

    /**
     * @param  list<string>  $fieldIds
     * @return list<PiiFieldData>
     */
    private function resolveTargets(string $tenantId, array $fieldIds): array
    {
        $targets = $this->piiFieldRepository->findActiveByIds($tenantId, $fieldIds);

        if ($fieldIds !== [] && count($targets) !== count(array_unique($fieldIds))) {
            throw new NotFoundHttpException('One or more requested PII field ids do not exist in the current tenant scope.');
        }

        return $targets;
    }

    /**
     * @param  list<PiiFieldData>  $targetFields
     * @param  list<PiiFieldData>  $updatedFields
     */
    private function buildReport(
        string $type,
        string $tenantId,
        array $targetFields,
        array $updatedFields,
        CarbonImmutable $now,
        bool $registryOnly,
    ): ComplianceReportData {
        $fieldIds = array_map(
            static fn (PiiFieldData $field): string => $field->fieldId,
            $targetFields,
        );
        $countsByObjectType = [];

        foreach ($targetFields as $field) {
            $countsByObjectType[$field->objectType] = ($countsByObjectType[$field->objectType] ?? 0) + 1;
        }

        ksort($countsByObjectType);

        return new ComplianceReportData(
            reportId: (string) Str::orderedUuid(),
            tenantId: $tenantId,
            type: $type,
            status: 'completed',
            requestedFieldCount: count($targetFields),
            processedFieldCount: count($updatedFields),
            skippedFieldCount: max(count($targetFields) - count($updatedFields), 0),
            fieldIds: $fieldIds,
            summary: [
                'object_type_counts' => $countsByObjectType,
                'registry_only' => $registryOnly,
            ],
            createdAt: $now,
            completedAt: $now,
        );
    }

    /**
     * @param  list<string>  $fieldIds
     * @return list<string>
     */
    private function normalizeFieldIds(array $fieldIds): array
    {
        $normalized = [];

        foreach ($fieldIds as $fieldId) {
            if ($fieldId !== '') {
                $normalized[] = $fieldId;
            }
        }

        return array_values(array_unique($normalized));
    }
}
