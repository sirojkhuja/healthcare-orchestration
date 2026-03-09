<?php

namespace App\Modules\Patient\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Patient\Application\Contracts\PatientContactRepository;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientContactData;
use App\Modules\Patient\Application\Data\PatientData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PatientContactService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly PatientContactRepository $patientContactRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $patientId, array $attributes): PatientContactData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $normalized = $this->normalizeCreateAttributes($attributes);

        /** @var PatientContactData $contact */
        $contact = DB::transaction(function () use ($tenantId, $patient, $normalized): PatientContactData {
            if ($normalized['is_primary']) {
                $this->patientContactRepository->clearPrimaryForPatient($tenantId, $patient->patientId);
            }

            return $this->patientContactRepository->create($tenantId, $patient->patientId, $normalized);
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.contact_created',
            objectType: 'patient',
            objectId: $patient->patientId,
            after: ['contact' => $contact->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'contact_id' => $contact->contactId,
            ],
        ));

        return $contact;
    }

    public function delete(string $patientId, string $contactId): PatientContactData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $contact = $this->contactOrFail($patient->patientId, $contactId);

        if (! $this->patientContactRepository->delete($tenantId, $patient->patientId, $contact->contactId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.contact_deleted',
            objectType: 'patient',
            objectId: $patient->patientId,
            before: ['contact' => $contact->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'contact_id' => $contact->contactId,
            ],
        ));

        return $contact;
    }

    /**
     * @return list<PatientContactData>
     */
    public function list(string $patientId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);

        return $this->patientContactRepository->listForPatient($tenantId, $patient->patientId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $patientId, string $contactId, array $attributes): PatientContactData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $contact = $this->contactOrFail($patient->patientId, $contactId);
        $updates = $this->normalizeUpdateAttributes($contact, $attributes);

        if ($updates === []) {
            return $contact;
        }

        /** @var PatientContactData $updated */
        $updated = DB::transaction(function () use ($tenantId, $patient, $contact, $updates): PatientContactData {
            $isPrimary = array_key_exists('is_primary', $updates)
                ? (bool) $updates['is_primary']
                : $contact->isPrimary;

            if ($isPrimary === true) {
                $this->patientContactRepository->clearPrimaryForPatient($tenantId, $patient->patientId, $contact->contactId);
            }

            return $this->patientContactRepository->update(
                $tenantId,
                $patient->patientId,
                $contact->contactId,
                $updates,
            ) ?? throw new \LogicException('Updated patient contact could not be reloaded.');
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.contact_updated',
            objectType: 'patient',
            objectId: $patient->patientId,
            before: ['contact' => $contact->toArray()],
            after: ['contact' => $updated->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'contact_id' => $contact->contactId,
            ],
        ));

        return $updated;
    }

    private function assertContactChannel(?string $phone, ?string $email): void
    {
        if ($phone === null && $email === null) {
            throw new UnprocessableEntityHttpException('A patient contact must include at least one phone or email value.');
        }
    }

    private function contactOrFail(string $patientId, string $contactId): PatientContactData
    {
        $contact = $this->patientContactRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
            $contactId,
        );

        if (! $contact instanceof PatientContactData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $contact;
    }

    private function nullableString(mixed $value, bool $lowercase = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        return $lowercase ? mb_strtolower($normalized) : $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeCreateAttributes(array $attributes): array
    {
        $name = $this->nullableString($attributes['name'] ?? null);

        if ($name === null) {
            throw new UnprocessableEntityHttpException('Patient contact name is required.');
        }

        $phone = $this->nullableString($attributes['phone'] ?? null);
        $email = $this->nullableString($attributes['email'] ?? null, true);
        $this->assertContactChannel($phone, $email);

        return [
            'name' => $name,
            'relationship' => $this->nullableString($attributes['relationship'] ?? null),
            'phone' => $phone,
            'email' => $email,
            'is_primary' => (bool) ($attributes['is_primary'] ?? false),
            'is_emergency' => (bool) ($attributes['is_emergency'] ?? false),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeUpdateAttributes(PatientContactData $contact, array $attributes): array
    {
        $updates = [];
        $nextPhone = $contact->phone;
        $nextEmail = $contact->email;

        if (array_key_exists('name', $attributes)) {
            $name = $this->nullableString($attributes['name']);

            if ($name === null) {
                throw new UnprocessableEntityHttpException('Patient contact name is required.');
            }

            if ($name !== $contact->name) {
                $updates['name'] = $name;
            }
        }

        if (array_key_exists('relationship', $attributes)) {
            $relationship = $this->nullableString($attributes['relationship']);

            if ($relationship !== $contact->relationship) {
                $updates['relationship'] = $relationship;
            }
        }

        if (array_key_exists('phone', $attributes)) {
            $nextPhone = $this->nullableString($attributes['phone']);

            if ($nextPhone !== $contact->phone) {
                $updates['phone'] = $nextPhone;
            }
        }

        if (array_key_exists('email', $attributes)) {
            $nextEmail = $this->nullableString($attributes['email'], true);

            if ($nextEmail !== $contact->email) {
                $updates['email'] = $nextEmail;
            }
        }

        $this->assertContactChannel($nextPhone, $nextEmail);

        if (array_key_exists('is_primary', $attributes)) {
            $isPrimary = (bool) $attributes['is_primary'];

            if ($isPrimary !== $contact->isPrimary) {
                $updates['is_primary'] = $isPrimary;
            }
        }

        if (array_key_exists('is_emergency', $attributes)) {
            $isEmergency = (bool) $attributes['is_emergency'];

            if ($isEmergency !== $contact->isEmergency) {
                $updates['is_emergency'] = $isEmergency;
            }
        }

        if (array_key_exists('notes', $attributes)) {
            $notes = $this->nullableString($attributes['notes']);

            if ($notes !== $contact->notes) {
                $updates['notes'] = $notes;
            }
        }

        return $updates;
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
