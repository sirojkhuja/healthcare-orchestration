<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Modules\Treatment\Application\Contracts\TreatmentPlanRepository;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Domain\Encounters\EncounterStatus;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class EncounterAttributeNormalizer
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly TreatmentPlanRepository $treatmentPlanRepository,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly ClinicRepository $clinicRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     patient_id: string,
     *     provider_id: string,
     *     treatment_plan_id: ?string,
     *     appointment_id: ?string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     status: string,
     *     encountered_at: CarbonImmutable,
     *     timezone: string,
     *     chief_complaint: ?string,
     *     summary: ?string,
     *     notes: ?string,
     *     follow_up_instructions: ?string
     * }
     */
    public function normalizeCreate(array $attributes, string $tenantId): array
    {
        $normalized = [
            'patient_id' => $this->requiredTrimmedString($attributes['patient_id'] ?? null),
            'provider_id' => $this->requiredTrimmedString($attributes['provider_id'] ?? null),
            'treatment_plan_id' => $this->nullableTrimmedString($attributes['treatment_plan_id'] ?? null),
            'appointment_id' => $this->nullableTrimmedString($attributes['appointment_id'] ?? null),
            'clinic_id' => $this->nullableTrimmedString($attributes['clinic_id'] ?? null),
            'room_id' => $this->nullableTrimmedString($attributes['room_id'] ?? null),
            'status' => EncounterStatus::OPEN->value,
            'encountered_at' => CarbonImmutable::parse($this->requiredTrimmedString($attributes['encountered_at'] ?? null)),
            'timezone' => $this->requiredTrimmedString($attributes['timezone'] ?? null),
            'chief_complaint' => $this->nullableTrimmedString($attributes['chief_complaint'] ?? null),
            'summary' => $this->nullableTrimmedString($attributes['summary'] ?? null),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
            'follow_up_instructions' => $this->nullableTrimmedString($attributes['follow_up_instructions'] ?? null),
        ];

        $this->assertCandidate($tenantId, $normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(EncounterData $encounter, array $attributes): array
    {
        $candidate = [
            'patient_id' => array_key_exists('patient_id', $attributes)
                ? $this->requiredTrimmedString($attributes['patient_id'])
                : $encounter->patientId,
            'provider_id' => array_key_exists('provider_id', $attributes)
                ? $this->requiredTrimmedString($attributes['provider_id'])
                : $encounter->providerId,
            'treatment_plan_id' => array_key_exists('treatment_plan_id', $attributes)
                ? $this->nullableTrimmedString($attributes['treatment_plan_id'])
                : $encounter->treatmentPlanId,
            'appointment_id' => array_key_exists('appointment_id', $attributes)
                ? $this->nullableTrimmedString($attributes['appointment_id'])
                : $encounter->appointmentId,
            'clinic_id' => array_key_exists('clinic_id', $attributes)
                ? $this->nullableTrimmedString($attributes['clinic_id'])
                : $encounter->clinicId,
            'room_id' => array_key_exists('room_id', $attributes)
                ? $this->nullableTrimmedString($attributes['room_id'])
                : $encounter->roomId,
            'status' => array_key_exists('status', $attributes)
                ? $this->requiredTrimmedString($attributes['status'])
                : $encounter->status,
            'encountered_at' => array_key_exists('encountered_at', $attributes)
                ? CarbonImmutable::parse($this->requiredTrimmedString($attributes['encountered_at']))
                : $encounter->encounteredAt,
            'timezone' => array_key_exists('timezone', $attributes)
                ? $this->requiredTrimmedString($attributes['timezone'])
                : $encounter->timezone,
            'chief_complaint' => array_key_exists('chief_complaint', $attributes)
                ? $this->nullableTrimmedString($attributes['chief_complaint'])
                : $encounter->chiefComplaint,
            'summary' => array_key_exists('summary', $attributes)
                ? $this->nullableTrimmedString($attributes['summary'])
                : $encounter->summary,
            'notes' => array_key_exists('notes', $attributes)
                ? $this->nullableTrimmedString($attributes['notes'])
                : $encounter->notes,
            'follow_up_instructions' => array_key_exists('follow_up_instructions', $attributes)
                ? $this->nullableTrimmedString($attributes['follow_up_instructions'])
                : $encounter->followUpInstructions,
        ];

        $this->assertCandidate($encounter->tenantId, $candidate);

        $updates = [];

        foreach ([
            'patient_id' => $encounter->patientId,
            'provider_id' => $encounter->providerId,
            'treatment_plan_id' => $encounter->treatmentPlanId,
            'appointment_id' => $encounter->appointmentId,
            'clinic_id' => $encounter->clinicId,
            'room_id' => $encounter->roomId,
            'status' => $encounter->status,
            'timezone' => $encounter->timezone,
            'chief_complaint' => $encounter->chiefComplaint,
            'summary' => $encounter->summary,
            'notes' => $encounter->notes,
            'follow_up_instructions' => $encounter->followUpInstructions,
        ] as $key => $current) {
            if ($candidate[$key] !== $current) {
                $updates[$key] = $candidate[$key];
            }
        }

        if (! $candidate['encountered_at']->equalTo($encounter->encounteredAt)) {
            $updates['encountered_at'] = $candidate['encountered_at'];
        }

        return $updates;
    }

    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     treatment_plan_id: ?string,
     *     appointment_id: ?string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     status: string,
     *     encountered_at: CarbonImmutable,
     *     timezone: string,
     *     chief_complaint: ?string,
     *     summary: ?string,
     *     notes: ?string,
     *     follow_up_instructions: ?string
     * }  $candidate
     */
    private function assertCandidate(string $tenantId, array $candidate): void
    {
        if (! in_array($candidate['status'], EncounterStatus::all(), true)) {
            throw new UnprocessableEntityHttpException('The status field must contain a supported encounter status.');
        }

        if ($this->patientRepository->findInTenant($tenantId, $candidate['patient_id']) === null) {
            throw new UnprocessableEntityHttpException('The patient_id field must reference an active patient in the current tenant.');
        }

        $provider = $this->providerRepository->findInTenant($tenantId, $candidate['provider_id']);

        if ($provider === null) {
            throw new UnprocessableEntityHttpException('The provider_id field must reference an active provider in the current tenant.');
        }

        if (
            $candidate['treatment_plan_id'] !== null
            && $this->treatmentPlanRepository->findInTenant($tenantId, $candidate['treatment_plan_id']) === null
        ) {
            throw new UnprocessableEntityHttpException('The treatment_plan_id field must reference an active treatment plan in the current tenant.');
        }

        if ($candidate['clinic_id'] !== null && $this->clinicRepository->findClinic($tenantId, $candidate['clinic_id']) === null) {
            throw new UnprocessableEntityHttpException('The clinic_id field must reference an existing clinic in the current tenant.');
        }

        if ($candidate['clinic_id'] !== null && $provider->clinicId !== null && $candidate['clinic_id'] !== $provider->clinicId) {
            throw new UnprocessableEntityHttpException('The clinic_id field must match the provider clinic assignment when the provider is already assigned to a clinic.');
        }

        if ($candidate['room_id'] !== null) {
            if ($candidate['clinic_id'] === null) {
                throw new UnprocessableEntityHttpException('The room_id field requires clinic_id.');
            }

            if ($this->clinicRepository->findRoom($tenantId, $candidate['clinic_id'], $candidate['room_id']) === null) {
                throw new UnprocessableEntityHttpException('The room_id field must reference an existing room in the selected clinic and tenant.');
            }
        }

        if ($candidate['appointment_id'] === null) {
            return;
        }

        $appointment = $this->appointmentRepository->findInTenant($tenantId, $candidate['appointment_id']);

        if ($appointment === null) {
            throw new UnprocessableEntityHttpException('The appointment_id field must reference an active appointment in the current tenant.');
        }

        if ($appointment->patientId !== $candidate['patient_id']) {
            throw new UnprocessableEntityHttpException('The patient_id field must match the linked appointment patient.');
        }

        if ($appointment->providerId !== $candidate['provider_id']) {
            throw new UnprocessableEntityHttpException('The provider_id field must match the linked appointment provider.');
        }

        if ($appointment->clinicId !== null && $candidate['clinic_id'] !== null && $appointment->clinicId !== $candidate['clinic_id']) {
            throw new UnprocessableEntityHttpException('The clinic_id field must match the linked appointment clinic.');
        }

        if ($appointment->roomId !== null && $candidate['room_id'] !== null && $appointment->roomId !== $candidate['room_id']) {
            throw new UnprocessableEntityHttpException('The room_id field must match the linked appointment room.');
        }
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredTrimmedString(mixed $value): string
    {
        return $this->nullableTrimmedString($value) ?? '';
    }
}
