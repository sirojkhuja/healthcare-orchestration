<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\InvoiceRepository;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Data\InvoiceExportData;
use App\Modules\Billing\Application\Data\InvoiceSearchCriteria;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InvoiceReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly FileStorageManager $fileStorageManager,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function export(InvoiceSearchCriteria $criteria, string $format): InvoiceExportData
    {
        if ($format !== 'csv') {
            throw new UnprocessableEntityHttpException('Only csv export is currently supported for invoices.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $invoices = $this->invoiceRepository->search($tenantId, $criteria);
        $generatedAt = CarbonImmutable::now();
        $exportId = (string) Str::uuid();
        $fileName = sprintf('invoices-%s.csv', $generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($invoices, $generatedAt),
            sprintf('tenants/%s/invoices/exports/%s/%s', $tenantId, $generatedAt->format('Y/m/d'), $fileName),
        );
        $export = new InvoiceExportData(
            exportId: $exportId,
            format: $format,
            fileName: $fileName,
            rowCount: count($invoices),
            generatedAt: $generatedAt,
            filters: $criteria,
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'invoices.exported',
            objectType: 'invoice_export',
            objectId: $exportId,
            after: $export->toArray(),
            metadata: [
                'tenant_id' => $tenantId,
                'filters' => $criteria->toArray(),
            ],
        ));

        return $export;
    }

    /**
     * @return list<InvoiceData>
     */
    public function list(InvoiceSearchCriteria $criteria): array
    {
        return $this->invoiceRepository->search(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @return list<InvoiceData>
     */
    public function search(InvoiceSearchCriteria $criteria): array
    {
        return $this->list($criteria);
    }

    /**
     * @param  list<InvoiceData>  $invoices
     */
    private function buildCsv(array $invoices, CarbonImmutable $generatedAt): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new UnprocessableEntityHttpException('Invoice export could not allocate a CSV stream.');
        }

        fputcsv($stream, [
            'invoice_id',
            'invoice_number',
            'status',
            'patient_id',
            'patient_display_name',
            'price_list_id',
            'price_list_code',
            'currency',
            'invoice_date',
            'due_on',
            'item_count',
            'subtotal_amount',
            'total_amount',
            'issued_at',
            'finalized_at',
            'voided_at',
            'void_reason',
            'notes',
            'created_at',
            'updated_at',
            'exported_at',
        ]);

        foreach ($invoices as $invoice) {
            fputcsv($stream, [
                $invoice->invoiceId,
                $invoice->invoiceNumber,
                $invoice->status,
                $invoice->patientId,
                $invoice->patientDisplayName,
                $invoice->priceListId,
                $invoice->priceListCode,
                $invoice->currency,
                $invoice->invoiceDate->toDateString(),
                $invoice->dueOn?->toDateString(),
                $invoice->itemCount,
                $invoice->subtotalAmount,
                $invoice->totalAmount,
                $invoice->issuedAt?->toIso8601String(),
                $invoice->finalizedAt?->toIso8601String(),
                $invoice->voidedAt?->toIso8601String(),
                $invoice->voidReason,
                $invoice->notes,
                $invoice->createdAt->toIso8601String(),
                $invoice->updatedAt->toIso8601String(),
                $generatedAt->toIso8601String(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new UnprocessableEntityHttpException('Invoice export could not be generated.');
        }

        return $contents;
    }
}
