<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Insurance\Application\Contracts\PatientInsurancePolicyRepository;
use App\Modules\Insurance\Application\Data\PatientInsurancePolicyData;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PatientInsuranceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly PatientInsurancePolicyRepository $patientInsurancePolicyRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attach(string $patientId, array $attributes): PatientInsurancePolicyData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $normalized = $this->normalizeCreateAttributes($attributes);

        /** @var PatientInsurancePolicyData $policy */
        $policy = DB::transaction(function () use ($tenantId, $patient, $normalized): PatientInsurancePolicyData {
            if ($this->patientInsurancePolicyRepository->existsDuplicate(
                $tenantId,
                $patient->patientId,
                $normalized['insurance_code'],
                $normalized['policy_number'],
            )) {
                throw new ConflictHttpException('The insurance policy is already attached to the patient.');
            }

            if ($normalized['is_primary']) {
                $this->patientInsurancePolicyRepository->clearPrimaryForPatient($tenantId, $patient->patientId);
            }

            return $this->patientInsurancePolicyRepository->create($tenantId, $patient->patientId, $normalized);
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.insurance_attached',
            objectType: 'patient',
            objectId: $patient->patientId,
            after: ['insurance' => $policy->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'policy_id' => $policy->policyId,
            ],
        ));

        return $policy;
    }

    public function delete(string $patientId, string $policyId): PatientInsurancePolicyData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $policy = $this->policyOrFail($patient->patientId, $policyId);

        if (! $this->patientInsurancePolicyRepository->delete($tenantId, $patient->patientId, $policy->policyId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.insurance_detached',
            objectType: 'patient',
            objectId: $patient->patientId,
            before: ['insurance' => $policy->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'policy_id' => $policy->policyId,
            ],
        ));

        return $policy;
    }

    /**
     * @return list<PatientInsurancePolicyData>
     */
    public function list(string $patientId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);

        return $this->patientInsurancePolicyRepository->listForPatient($tenantId, $patient->patientId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     insurance_code: string,
     *     policy_number: string,
     *     member_number: ?string,
     *     group_number: ?string,
     *     plan_name: ?string,
     *     effective_from: ?string,
     *     effective_to: ?string,
     *     is_primary: bool,
     *     notes: ?string
     * }
     */
    private function normalizeCreateAttributes(array $attributes): array
    {
        $effectiveFrom = $this->nullableDate($attributes['effective_from'] ?? null);
        $effectiveTo = $this->nullableDate($attributes['effective_to'] ?? null);

        if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveTo->lt($effectiveFrom)) {
            throw new UnprocessableEntityHttpException('Insurance effective end date must not be earlier than the start date.');
        }

        return [
            'insurance_code' => mb_strtolower($this->requiredString($attributes['insurance_code'] ?? null, 'insurance_code')),
            'policy_number' => $this->requiredString($attributes['policy_number'] ?? null, 'policy_number'),
            'member_number' => $this->nullableString($attributes['member_number'] ?? null),
            'group_number' => $this->nullableString($attributes['group_number'] ?? null),
            'plan_name' => $this->nullableString($attributes['plan_name'] ?? null),
            'effective_from' => $effectiveFrom?->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'is_primary' => (bool) ($attributes['is_primary'] ?? false),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
    }

    private function policyOrFail(string $patientId, string $policyId): PatientInsurancePolicyData
    {
        $policy = $this->patientInsurancePolicyRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
            $policyId,
        );

        if (! $policy instanceof PatientInsurancePolicyData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $policy;
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

    private function nullableDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
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
