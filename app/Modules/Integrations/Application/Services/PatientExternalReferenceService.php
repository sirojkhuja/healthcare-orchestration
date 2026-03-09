<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Integrations\Application\Contracts\PatientExternalReferenceRepository;
use App\Modules\Integrations\Application\Data\PatientExternalReferenceData;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PatientExternalReferenceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly PatientExternalReferenceRepository $patientExternalReferenceRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attach(string $patientId, array $attributes): PatientExternalReferenceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $normalized = $this->normalizeCreateAttributes($attributes);

        /** @var PatientExternalReferenceData $reference */
        $reference = DB::transaction(function () use ($tenantId, $patient, $normalized): PatientExternalReferenceData {
            if ($this->patientExternalReferenceRepository->existsDuplicate(
                $tenantId,
                $patient->patientId,
                $normalized['integration_key'],
                $normalized['external_type'],
                $normalized['external_id'],
            )) {
                throw new ConflictHttpException('The external reference already exists for the patient.');
            }

            return $this->patientExternalReferenceRepository->create($tenantId, $patient->patientId, $normalized);
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.external_ref_attached',
            objectType: 'patient',
            objectId: $patient->patientId,
            after: ['external_ref' => $reference->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'ref_id' => $reference->referenceId,
            ],
        ));

        return $reference;
    }

    public function delete(string $patientId, string $referenceId): PatientExternalReferenceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $reference = $this->referenceOrFail($patient->patientId, $referenceId);

        if (! $this->patientExternalReferenceRepository->delete($tenantId, $patient->patientId, $reference->referenceId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.external_ref_detached',
            objectType: 'patient',
            objectId: $patient->patientId,
            before: ['external_ref' => $reference->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'ref_id' => $reference->referenceId,
            ],
        ));

        return $reference;
    }

    /**
     * @return list<PatientExternalReferenceData>
     */
    public function list(string $patientId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);

        return $this->patientExternalReferenceRepository->listForPatient($tenantId, $patient->patientId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     integration_key: string,
     *     external_id: string,
     *     external_type: string,
     *     display_name: ?string,
     *     metadata: array<string, mixed>,
     *     linked_at: CarbonImmutable
     * }
     */
    private function normalizeCreateAttributes(array $attributes): array
    {
        return [
            'integration_key' => $this->normalizedKey($attributes['integration_key'] ?? null, 'integration_key'),
            'external_id' => $this->requiredString($attributes['external_id'] ?? null, 'external_id'),
            'external_type' => $this->normalizedKey($attributes['external_type'] ?? 'patient', 'external_type'),
            'display_name' => $this->nullableString($attributes['display_name'] ?? null),
            'metadata' => $this->metadataValue($attributes['metadata'] ?? null),
            'linked_at' => CarbonImmutable::now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function normalizedKey(mixed $value, string $label): string
    {
        $string = $this->requiredString($value, $label);
        $normalized = preg_replace('/[^a-z0-9._-]+/', '_', mb_strtolower($string));
        $result = trim(is_string($normalized) ? $normalized : '', '_');

        if ($result === '') {
            throw new UnprocessableEntityHttpException('The '.$label.' value is not valid.');
        }

        return $result;
    }

    private function referenceOrFail(string $patientId, string $referenceId): PatientExternalReferenceData
    {
        $reference = $this->patientExternalReferenceRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
            $referenceId,
        );

        if (! $reference instanceof PatientExternalReferenceData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $reference;
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredString(mixed $value, string $label): string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            throw new UnprocessableEntityHttpException('The '.$label.' field is required.');
        }

        return $normalized;
    }
}
