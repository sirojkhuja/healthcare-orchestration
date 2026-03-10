<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Pharmacy\Application\Contracts\MedicationRepository;
use App\Modules\Pharmacy\Application\Contracts\PatientAllergyRepository;
use App\Modules\Pharmacy\Application\Data\MedicationData;
use App\Modules\Pharmacy\Application\Data\PatientAllergyData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PatientAllergyService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly PatientAllergyRepository $patientAllergyRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $patientId, array $attributes): PatientAllergyData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $medication = $this->medicationFromAttribute($tenantId, $attributes['medication_id'] ?? null);
        $normalized = $this->normalizeCreateAttributes($attributes, $medication);

        if ($this->patientAllergyRepository->allergenExists($tenantId, $patient->patientId, $normalized['allergen_name'])) {
            throw new ConflictHttpException('An allergy with this allergen_name already exists for the patient.');
        }

        $allergy = $this->patientAllergyRepository->create($tenantId, $patient->patientId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patient_allergies.created',
            objectType: 'patient_allergy',
            objectId: $allergy->allergyId,
            after: $allergy->toArray(),
            metadata: [
                'patient_id' => $patient->patientId,
                'allergy_id' => $allergy->allergyId,
            ],
        ));

        return $allergy;
    }

    public function delete(string $patientId, string $allergyId): PatientAllergyData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $allergy = $this->allergyOrFail($patient->patientId, $allergyId);

        if (! $this->patientAllergyRepository->delete($tenantId, $patient->patientId, $allergyId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patient_allergies.deleted',
            objectType: 'patient_allergy',
            objectId: $allergy->allergyId,
            before: $allergy->toArray(),
            metadata: [
                'patient_id' => $patient->patientId,
                'allergy_id' => $allergy->allergyId,
            ],
        ));

        return $allergy;
    }

    /**
     * @return list<PatientAllergyData>
     */
    public function list(string $patientId): array
    {
        $patient = $this->patientOrFail($patientId);
        $allergies = $this->patientAllergyRepository->listForPatient(
            $this->tenantContext->requireTenantId(),
            $patient->patientId,
        );

        usort($allergies, function (PatientAllergyData $left, PatientAllergyData $right): int {
            $severity = $this->severityRank($right->severity) <=> $this->severityRank($left->severity);

            if ($severity !== 0) {
                return $severity;
            }

            $leftNotedAt = $left->notedAt?->getTimestamp();
            $rightNotedAt = $right->notedAt?->getTimestamp();

            if ($leftNotedAt !== $rightNotedAt) {
                return ($rightNotedAt ?? PHP_INT_MIN) <=> ($leftNotedAt ?? PHP_INT_MIN);
            }

            $name = strcmp($left->allergenName, $right->allergenName);

            if ($name !== 0) {
                return $name;
            }

            return $left->createdAt->getTimestamp() <=> $right->createdAt->getTimestamp();
        });

        return $allergies;
    }

    private function allergyOrFail(string $patientId, string $allergyId): PatientAllergyData
    {
        $allergy = $this->patientAllergyRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
            $allergyId,
        );

        if (! $allergy instanceof PatientAllergyData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $allergy;
    }

    private function medicationFromAttribute(string $tenantId, mixed $value): ?MedicationData
    {
        $medicationId = $this->nullableString($value);

        if ($medicationId === null) {
            return null;
        }

        $medication = $this->medicationRepository->findInTenant($tenantId, $medicationId);

        if (! $medication instanceof MedicationData) {
            throw new UnprocessableEntityHttpException('The medication_id field must reference an active medication in the current tenant.');
        }

        return $medication;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     medication_id: ?string,
     *     allergen_name: string,
     *     reaction: ?string,
     *     severity: ?string,
     *     noted_at: ?CarbonImmutable,
     *     notes: ?string
     * }
     */
    private function normalizeCreateAttributes(array $attributes, ?MedicationData $medication): array
    {
        $allergenName = $this->nullableString($attributes['allergen_name'] ?? null)
            ?? $medication?->name;

        if ($allergenName === null) {
            throw new UnprocessableEntityHttpException('The allergen_name field is required when medication_id is not present.');
        }

        return [
            'medication_id' => $medication?->medicationId,
            'allergen_name' => $allergenName,
            'reaction' => $this->nullableString($attributes['reaction'] ?? null),
            'severity' => $this->normalizedSeverity($attributes['severity'] ?? null),
            'noted_at' => $this->nullableDateTime($attributes['noted_at'] ?? null),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
    }

    private function normalizedSeverity(mixed $value): ?string
    {
        $severity = $this->nullableString($value);

        if ($severity === null) {
            return null;
        }

        $normalized = mb_strtolower($severity);
        $allowed = ['mild', 'moderate', 'severe', 'life_threatening'];

        if (! in_array($normalized, $allowed, true)) {
            throw new UnprocessableEntityHttpException('The severity field is not valid.');
        }

        return $normalized;
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

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        $normalized = $this->nullableString($value);

        return $normalized === null ? null : CarbonImmutable::parse($normalized);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function severityRank(?string $severity): int
    {
        return match ($severity) {
            'life_threatening' => 4,
            'severe' => 3,
            'moderate' => 2,
            'mild' => 1,
            default => 0,
        };
    }
}
