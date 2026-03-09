<?php

namespace App\Modules\Patient\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Contracts\PatientTagRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Data\PatientTagListData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PatientTagService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly PatientTagRepository $patientTagRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function list(string $patientId): PatientTagListData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);

        return new PatientTagListData(
            patientId: $patient->patientId,
            tags: $this->patientTagRepository->listForPatient($tenantId, $patient->patientId),
        );
    }

    /**
     * @param  list<string>  $tags
     */
    public function replace(string $patientId, array $tags): PatientTagListData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $existing = $this->patientTagRepository->listForPatient($tenantId, $patient->patientId);
        $normalizedTags = $this->normalizeTags($tags);

        if ($existing !== $normalizedTags) {
            DB::transaction(function () use ($tenantId, $patient, $normalizedTags): void {
                $this->patientTagRepository->replaceForPatient($tenantId, $patient->patientId, $normalizedTags);
            });

            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'patients.tags_updated',
                objectType: 'patient',
                objectId: $patient->patientId,
                before: ['tags' => $existing],
                after: ['tags' => $normalizedTags],
                metadata: [
                    'patient_id' => $patient->patientId,
                    'tag_count' => count($normalizedTags),
                ],
            ));
        }

        return new PatientTagListData($patient->patientId, $normalizedTags);
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        $normalized = [];

        foreach ($tags as $tag) {
            $value = preg_replace('/\s+/', ' ', mb_strtolower(trim($tag)));

            if (! is_string($value) || $value === '') {
                continue;
            }

            $normalized[$value] = true;
        }

        $values = array_keys($normalized);
        sort($values);

        return $values;
    }

    private function patientOrFail(string $patientId): PatientData
    {
        $patient = $this->patientRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
        );

        if (! $patient instanceof PatientData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $patient;
    }
}
