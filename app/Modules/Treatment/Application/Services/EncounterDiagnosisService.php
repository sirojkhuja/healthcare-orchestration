<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Treatment\Application\Contracts\EncounterDiagnosisRepository;
use App\Modules\Treatment\Application\Contracts\EncounterRepository;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Data\EncounterDiagnosisData;
use App\Modules\Treatment\Domain\Encounters\DiagnosisType;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EncounterDiagnosisService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EncounterRepository $encounterRepository,
        private readonly EncounterDiagnosisRepository $encounterDiagnosisRepository,
        private readonly EncounterDiagnosisAttributeNormalizer $encounterDiagnosisAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $encounterId, array $attributes): EncounterDiagnosisData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterOrFail($encounterId);
        $normalized = $this->encounterDiagnosisAttributeNormalizer->normalizeCreate($attributes);

        if (
            $normalized['diagnosis_type'] === DiagnosisType::PRIMARY->value
            && $this->encounterDiagnosisRepository->primaryDiagnosisExists($tenantId, $encounter->encounterId)
        ) {
            throw new ConflictHttpException('Only one primary diagnosis may exist per encounter.');
        }

        if ($this->encounterDiagnosisRepository->duplicateExists(
            $tenantId,
            $encounter->encounterId,
            $normalized['code'],
            $normalized['display_name'],
            $normalized['diagnosis_type'],
        )) {
            throw new ConflictHttpException('The requested diagnosis already exists on this encounter.');
        }

        $diagnosis = $this->encounterDiagnosisRepository->create($tenantId, $encounter->encounterId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounter_diagnoses.added',
            objectType: 'encounter_diagnosis',
            objectId: $diagnosis->diagnosisId,
            after: $diagnosis->toArray(),
            metadata: [
                'encounter_id' => $encounter->encounterId,
            ],
        ));

        return $diagnosis;
    }

    public function delete(string $encounterId, string $diagnosisId): EncounterDiagnosisData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterOrFail($encounterId);
        $diagnosis = $this->encounterDiagnosisRepository->findInEncounter($tenantId, $encounter->encounterId, $diagnosisId);

        if (! $diagnosis instanceof EncounterDiagnosisData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        if (! $this->encounterDiagnosisRepository->delete($tenantId, $encounter->encounterId, $diagnosisId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounter_diagnoses.removed',
            objectType: 'encounter_diagnosis',
            objectId: $diagnosis->diagnosisId,
            before: $diagnosis->toArray(),
            metadata: [
                'encounter_id' => $encounter->encounterId,
            ],
        ));

        return $diagnosis;
    }

    /**
     * @return list<EncounterDiagnosisData>
     */
    public function list(string $encounterId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $encounter = $this->encounterOrFail($encounterId);

        return $this->encounterDiagnosisRepository->listForEncounter($tenantId, $encounter->encounterId);
    }

    private function encounterOrFail(string $encounterId): EncounterData
    {
        $encounter = $this->encounterRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $encounterId,
        );

        if (! $encounter instanceof EncounterData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $encounter;
    }
}
