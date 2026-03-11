<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Insurance\Application\Contracts\ClaimRepository;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Data\ClaimExportData;
use App\Modules\Insurance\Application\Data\ClaimSearchCriteria;
use App\Modules\Insurance\Application\Queries\ExportClaimsQuery;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClaimReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClaimRepository $claimRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly FileStorageManager $fileStorageManager,
    ) {}

    public function export(ExportClaimsQuery $query): ClaimExportData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $claims = $this->search($query->criteria);
        $generatedAt = CarbonImmutable::now();
        $fileName = sprintf('claims-%s.csv', $generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($claims),
            sprintf('tenants/%s/claims/exports/%s/%s', $tenantId, $generatedAt->format('Y/m/d'), $fileName),
        );
        $export = new ClaimExportData(
            exportId: (string) Str::uuid(),
            format: $query->format,
            fileName: $fileName,
            rowCount: count($claims),
            generatedAt: $generatedAt,
            filters: $query->criteria,
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'claims.exported',
            objectType: 'claim_export',
            objectId: $export->exportId,
            after: $export->toArray(),
            metadata: [
                'tenant_id' => $tenantId,
                'filters' => $query->criteria->toArray(),
            ],
        ));

        return $export;
    }

    public function get(string $claimId): ClaimData
    {
        $claim = $this->claimRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $claimId,
        );

        if (! $claim instanceof ClaimData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $claim;
    }

    /**
     * @return list<ClaimData>
     */
    public function list(ClaimSearchCriteria $criteria): array
    {
        return $this->claimRepository->search($this->tenantContext->requireTenantId(), $criteria);
    }

    /**
     * @return list<ClaimData>
     */
    public function search(ClaimSearchCriteria $criteria): array
    {
        return $this->claimRepository->search($this->tenantContext->requireTenantId(), $criteria);
    }

    /**
     * @param  list<ClaimData>  $claims
     */
    private function buildCsv(array $claims): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to open the temporary CSV stream.');
        }

        fputcsv($stream, [
            'claim_number',
            'status',
            'payer_code',
            'payer_name',
            'patient_display_name',
            'invoice_number',
            'policy_number',
            'service_date',
            'billed_amount',
            'approved_amount',
            'paid_amount',
            'attachment_count',
            'created_at',
        ]);

        foreach ($claims as $claim) {
            fputcsv($stream, [
                $claim->claimNumber,
                $claim->status,
                $claim->payerCode,
                $claim->payerName,
                $claim->patientDisplayName,
                $claim->invoiceNumber,
                $claim->patientPolicyNumber,
                $claim->serviceDate->toDateString(),
                $claim->billedAmount,
                $claim->approvedAmount,
                $claim->paidAmount,
                $claim->attachmentCount,
                $claim->createdAt->toIso8601String(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }
}
