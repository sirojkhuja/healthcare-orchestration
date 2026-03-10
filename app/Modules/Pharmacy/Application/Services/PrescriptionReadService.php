<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Pharmacy\Application\Contracts\PrescriptionRepository;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Data\PrescriptionExportData;
use App\Modules\Pharmacy\Application\Data\PrescriptionSearchCriteria;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PrescriptionReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PrescriptionRepository $prescriptionRepository,
        private readonly FileStorageManager $fileStorageManager,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function export(PrescriptionSearchCriteria $criteria, string $format): PrescriptionExportData
    {
        if ($format !== 'csv') {
            throw new UnprocessableEntityHttpException('Only csv export is currently supported for prescriptions.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $prescriptions = $this->prescriptionRepository->search($tenantId, $criteria);
        $generatedAt = CarbonImmutable::now();
        $exportId = (string) Str::uuid();
        $fileName = sprintf('prescriptions-%s.csv', $generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($prescriptions, $generatedAt),
            sprintf('tenants/%s/prescriptions/exports/%s/%s', $tenantId, $generatedAt->format('Y/m/d'), $fileName),
        );
        $export = new PrescriptionExportData(
            exportId: $exportId,
            format: $format,
            fileName: $fileName,
            rowCount: count($prescriptions),
            generatedAt: $generatedAt,
            filters: $criteria,
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'prescriptions.exported',
            objectType: 'prescription_export',
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
     * @return list<PrescriptionData>
     */
    public function list(PrescriptionSearchCriteria $criteria): array
    {
        return $this->prescriptionRepository->search(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @return list<PrescriptionData>
     */
    public function search(PrescriptionSearchCriteria $criteria): array
    {
        return $this->list($criteria);
    }

    /**
     * @param  list<PrescriptionData>  $prescriptions
     */
    private function buildCsv(array $prescriptions, CarbonImmutable $generatedAt): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new UnprocessableEntityHttpException('Prescription export could not allocate a CSV stream.');
        }

        fputcsv($stream, [
            'prescription_id',
            'status',
            'patient_id',
            'patient_display_name',
            'provider_id',
            'provider_display_name',
            'encounter_id',
            'treatment_item_id',
            'medication_name',
            'medication_code',
            'dosage',
            'route',
            'frequency',
            'quantity',
            'quantity_unit',
            'authorized_refills',
            'starts_on',
            'ends_on',
            'issued_at',
            'dispensed_at',
            'canceled_at',
            'cancel_reason',
            'notes',
            'exported_at',
        ]);

        foreach ($prescriptions as $prescription) {
            fputcsv($stream, [
                $prescription->prescriptionId,
                $prescription->status,
                $prescription->patientId,
                $prescription->patientDisplayName,
                $prescription->providerId,
                $prescription->providerDisplayName,
                $prescription->encounterId,
                $prescription->treatmentItemId,
                $prescription->medicationName,
                $prescription->medicationCode,
                $prescription->dosage,
                $prescription->route,
                $prescription->frequency,
                $prescription->quantity,
                $prescription->quantityUnit,
                $prescription->authorizedRefills,
                $prescription->startsOn,
                $prescription->endsOn,
                $prescription->issuedAt?->toIso8601String(),
                $prescription->dispensedAt?->toIso8601String(),
                $prescription->canceledAt?->toIso8601String(),
                $prescription->cancelReason,
                $prescription->notes,
                $generatedAt->toIso8601String(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new UnprocessableEntityHttpException('Prescription export could not be generated.');
        }

        return $contents;
    }
}
