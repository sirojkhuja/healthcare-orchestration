<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Lab\Application\Contracts\LabOrderRepository;
use App\Modules\Lab\Application\Contracts\LabResultRepository;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabOrderExportData;
use App\Modules\Lab\Application\Data\LabOrderSearchCriteria;
use App\Modules\Lab\Application\Data\LabResultData;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class LabOrderReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LabOrderRepository $labOrderRepository,
        private readonly LabResultRepository $labResultRepository,
        private readonly FileStorageManager $fileStorageManager,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function export(LabOrderSearchCriteria $criteria, string $format): LabOrderExportData
    {
        if ($format !== 'csv') {
            throw new UnprocessableEntityHttpException('Only csv export is currently supported for lab orders.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $orders = $this->labOrderRepository->search($tenantId, $criteria);
        $generatedAt = CarbonImmutable::now();
        $exportId = (string) Str::uuid();
        $fileName = sprintf('lab-orders-%s.csv', $generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($orders, $generatedAt),
            sprintf('tenants/%s/lab-orders/exports/%s/%s', $tenantId, $generatedAt->format('Y/m/d'), $fileName),
        );
        $export = new LabOrderExportData(
            exportId: $exportId,
            format: $format,
            fileName: $fileName,
            rowCount: count($orders),
            generatedAt: $generatedAt,
            filters: $criteria,
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_orders.exported',
            objectType: 'lab_order_export',
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
     * @return list<LabOrderData>
     */
    public function list(LabOrderSearchCriteria $criteria): array
    {
        return $this->labOrderRepository->search(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @return list<LabResultData>
     */
    public function listResults(string $orderId): array
    {
        $order = $this->orderOrFail($orderId);

        return $this->labResultRepository->listForOrder($order->tenantId, $order->orderId);
    }

    /**
     * @return list<LabOrderData>
     */
    public function search(LabOrderSearchCriteria $criteria): array
    {
        return $this->list($criteria);
    }

    public function showResult(string $orderId, string $resultId): LabResultData
    {
        $order = $this->orderOrFail($orderId);
        $result = $this->labResultRepository->findInOrder($order->tenantId, $order->orderId, $resultId);

        if (! $result instanceof LabResultData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $result;
    }

    /**
     * @param  list<LabOrderData>  $orders
     */
    private function buildCsv(array $orders, CarbonImmutable $generatedAt): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new UnprocessableEntityHttpException('Lab order export could not allocate a CSV stream.');
        }

        fputcsv($stream, [
            'order_id',
            'status',
            'lab_provider_key',
            'external_order_id',
            'patient_id',
            'patient_display_name',
            'provider_id',
            'provider_display_name',
            'encounter_id',
            'lab_test_id',
            'requested_test_code',
            'requested_test_name',
            'requested_specimen_type',
            'ordered_at',
            'timezone',
            'sent_at',
            'specimen_collected_at',
            'specimen_received_at',
            'completed_at',
            'canceled_at',
            'cancel_reason',
            'result_count',
            'notes',
            'exported_at',
        ]);

        foreach ($orders as $order) {
            fputcsv($stream, [
                $order->orderId,
                $order->status,
                $order->labProviderKey,
                $order->externalOrderId,
                $order->patientId,
                $order->patientDisplayName,
                $order->providerId,
                $order->providerDisplayName,
                $order->encounterId,
                $order->labTestId,
                $order->requestedTestCode,
                $order->requestedTestName,
                $order->requestedSpecimenType,
                $order->orderedAt->toIso8601String(),
                $order->timezone,
                $order->sentAt?->toIso8601String(),
                $order->specimenCollectedAt?->toIso8601String(),
                $order->specimenReceivedAt?->toIso8601String(),
                $order->completedAt?->toIso8601String(),
                $order->canceledAt?->toIso8601String(),
                $order->cancelReason,
                $order->resultCount,
                $order->notes,
                $generatedAt->toIso8601String(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new UnprocessableEntityHttpException('Lab order export could not be generated.');
        }

        return $contents;
    }

    private function orderOrFail(string $orderId): LabOrderData
    {
        $order = $this->labOrderRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $orderId,
        );

        if (! $order instanceof LabOrderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $order;
    }
}
