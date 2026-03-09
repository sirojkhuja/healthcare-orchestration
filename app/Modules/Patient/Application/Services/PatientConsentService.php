<?php

namespace App\Modules\Patient\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Patient\Application\Contracts\PatientConsentRepository;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientConsentData;
use App\Modules\Patient\Application\Data\PatientData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PatientConsentService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly PatientConsentRepository $patientConsentRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $patientId, array $attributes): PatientConsentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $normalized = $this->normalizeCreateAttributes($attributes);

        /** @var PatientConsentData $consent */
        $consent = DB::transaction(function () use ($tenantId, $patient, $normalized): PatientConsentData {
            if ($this->patientConsentRepository->hasActiveConsentType(
                $tenantId,
                $patient->patientId,
                $normalized['consent_type'],
                CarbonImmutable::now(),
            )) {
                throw new ConflictHttpException('An active consent of this type already exists for the patient.');
            }

            return $this->patientConsentRepository->create($tenantId, $patient->patientId, $normalized);
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.consent_created',
            objectType: 'patient',
            objectId: $patient->patientId,
            after: ['consent' => $consent->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'consent_id' => $consent->consentId,
            ],
        ));

        return $consent;
    }

    /**
     * @return list<PatientConsentData>
     */
    public function list(string $patientId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $consents = $this->patientConsentRepository->listForPatient($tenantId, $patient->patientId);

        usort($consents, static function (PatientConsentData $left, PatientConsentData $right): int {
            $leftActive = $left->status() === 'active' ? 1 : 0;
            $rightActive = $right->status() === 'active' ? 1 : 0;

            if ($leftActive !== $rightActive) {
                return $rightActive <=> $leftActive;
            }

            $grantedComparison = $right->grantedAt->getTimestamp() <=> $left->grantedAt->getTimestamp();

            if ($grantedComparison !== 0) {
                return $grantedComparison;
            }

            return $right->createdAt->getTimestamp() <=> $left->createdAt->getTimestamp();
        });

        return $consents;
    }

    public function revoke(string $patientId, string $consentId, ?string $reason): PatientConsentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $consent = $this->consentOrFail($patient->patientId, $consentId);

        if ($consent->revokedAt instanceof CarbonImmutable) {
            throw new ConflictHttpException('The patient consent has already been revoked.');
        }

        $revoked = $this->patientConsentRepository->revoke(
            $tenantId,
            $patient->patientId,
            $consent->consentId,
            CarbonImmutable::now(),
            $this->nullableString($reason),
        );

        if (! $revoked instanceof PatientConsentData) {
            throw new \LogicException('Revoked patient consent could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.consent_revoked',
            objectType: 'patient',
            objectId: $patient->patientId,
            before: ['consent' => $consent->toArray()],
            after: ['consent' => $revoked->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'consent_id' => $consent->consentId,
            ],
        ));

        return $revoked;
    }

    private function consentOrFail(string $patientId, string $consentId): PatientConsentData
    {
        $consent = $this->patientConsentRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
            $consentId,
        );

        if (! $consent instanceof PatientConsentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $consent;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     consent_type: string,
     *     granted_by_name: string,
     *     granted_by_relationship: ?string,
     *     granted_at: CarbonImmutable,
     *     expires_at: ?CarbonImmutable,
     *     notes: ?string
     * }
     */
    private function normalizeCreateAttributes(array $attributes): array
    {
        $grantedAt = $this->dateTimeOrNow($attributes['granted_at'] ?? null);
        $expiresAt = $this->nullableDateTime($attributes['expires_at'] ?? null);

        if ($expiresAt instanceof CarbonImmutable && $expiresAt->lessThanOrEqualTo($grantedAt)) {
            throw new UnprocessableEntityHttpException('Consent expiry must be later than the grant time.');
        }

        return [
            'consent_type' => $this->normalizedIdentifier($attributes['consent_type'] ?? null, 'consent type'),
            'granted_by_name' => $this->requiredString($attributes['granted_by_name'] ?? null, 'granted_by_name'),
            'granted_by_relationship' => $this->nullableString($attributes['granted_by_relationship'] ?? null),
            'granted_at' => $grantedAt,
            'expires_at' => $expiresAt,
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
    }

    private function normalizedIdentifier(mixed $value, string $label): string
    {
        $string = $this->requiredString($value, $label);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($string));
        $result = trim(is_string($normalized) ? $normalized : '', '_');

        if ($result === '') {
            throw new UnprocessableEntityHttpException('The '.$label.' value is not valid.');
        }

        return $result;
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

    private function dateTimeOrNow(mixed $value): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::now();
        }

        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException('The datetime value is not valid.');
        }

        return CarbonImmutable::parse($value);
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException('The datetime value is not valid.');
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
