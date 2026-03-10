<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Treatment\Application\Contracts\EncounterRepository;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Data\EncounterExportData;
use App\Modules\Treatment\Application\Data\EncounterListCriteria;
use App\Modules\Treatment\Application\Queries\ExportEncountersQuery;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class EncounterReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EncounterRepository $encounterRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly FileStorageManager $fileStorageManager,
    ) {}

    /**
     * @return list<EncounterData>
     */
    public function list(EncounterListCriteria $criteria): array
    {
        return $this->encounterRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    public function export(ExportEncountersQuery $query): EncounterExportData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounters = $this->list($query->criteria);
        $generatedAt = CarbonImmutable::now();
        $exportId = (string) Str::uuid();
        $fileName = sprintf('encounters-%s.csv', $generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($encounters, $generatedAt),
            sprintf('tenants/%s/encounters/exports/%s/%s', $tenantId, $generatedAt->format('Y/m/d'), $fileName),
        );
        $export = new EncounterExportData(
            exportId: $exportId,
            format: $query->format,
            fileName: $fileName,
            rowCount: count($encounters),
            generatedAt: $generatedAt,
            filters: $query->criteria,
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounters.exported',
            objectType: 'encounter_export',
            objectId: $exportId,
            after: $export->toArray(),
            metadata: [
                'tenant_id' => $tenantId,
                'filters' => $query->criteria->toArray(),
            ],
        ));

        return $export;
    }

    /**
     * @param  list<EncounterData>  $encounters
     */
    private function buildCsv(array $encounters, CarbonImmutable $generatedAt): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new UnprocessableEntityHttpException('Encounter export could not allocate a CSV stream.');
        }

        fputcsv($stream, [
            'id',
            'tenant_id',
            'status',
            'patient_id',
            'patient_name',
            'provider_id',
            'provider_name',
            'treatment_plan_id',
            'appointment_id',
            'clinic_id',
            'clinic_name',
            'room_id',
            'room_name',
            'encountered_at',
            'timezone',
            'chief_complaint',
            'summary',
            'diagnosis_count',
            'procedure_count',
            'created_at',
            'updated_at',
            'exported_at',
        ]);

        foreach ($encounters as $encounter) {
            fputcsv($stream, [
                $encounter->encounterId,
                $encounter->tenantId,
                $encounter->status,
                $encounter->patientId,
                $encounter->patientDisplayName,
                $encounter->providerId,
                $encounter->providerDisplayName,
                $encounter->treatmentPlanId,
                $encounter->appointmentId,
                $encounter->clinicId,
                $encounter->clinicName,
                $encounter->roomId,
                $encounter->roomName,
                $encounter->encounteredAt->toIso8601String(),
                $encounter->timezone,
                $encounter->chiefComplaint,
                $encounter->summary,
                $encounter->diagnosisCount,
                $encounter->procedureCount,
                $encounter->createdAt->toIso8601String(),
                $encounter->updatedAt->toIso8601String(),
                $generatedAt->toIso8601String(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new UnprocessableEntityHttpException('Encounter export could not be generated.');
        }

        return $contents;
    }
}
