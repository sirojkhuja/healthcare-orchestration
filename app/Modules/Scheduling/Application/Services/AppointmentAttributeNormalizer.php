<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AppointmentAttributeNormalizer
{
    public function __construct(
        private readonly PatientRepository $patientRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly ClinicRepository $clinicRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     scheduled_start_at: CarbonImmutable,
     *     scheduled_end_at: CarbonImmutable,
     *     timezone: string
     * }
     */
    public function normalizeCreate(array $attributes, string $tenantId): array
    {
        $normalized = [
            'patient_id' => $this->requiredTrimmedString($attributes['patient_id'] ?? null),
            'provider_id' => $this->requiredTrimmedString($attributes['provider_id'] ?? null),
            'clinic_id' => $this->nullableTrimmedString($attributes['clinic_id'] ?? null),
            'room_id' => $this->nullableTrimmedString($attributes['room_id'] ?? null),
            'scheduled_start_at' => CarbonImmutable::parse($this->requiredTrimmedString($attributes['scheduled_start_at'] ?? null)),
            'scheduled_end_at' => CarbonImmutable::parse($this->requiredTrimmedString($attributes['scheduled_end_at'] ?? null)),
            'timezone' => $this->requiredTrimmedString($attributes['timezone'] ?? null),
        ];

        $this->assertCandidate($tenantId, $normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizePatch(AppointmentData $appointment, array $attributes): array
    {
        $candidate = [
            'patient_id' => array_key_exists('patient_id', $attributes)
                ? $this->requiredTrimmedString($attributes['patient_id'])
                : $appointment->patientId,
            'provider_id' => array_key_exists('provider_id', $attributes)
                ? $this->requiredTrimmedString($attributes['provider_id'])
                : $appointment->providerId,
            'clinic_id' => array_key_exists('clinic_id', $attributes)
                ? $this->nullableTrimmedString($attributes['clinic_id'])
                : $appointment->clinicId,
            'room_id' => array_key_exists('room_id', $attributes)
                ? $this->nullableTrimmedString($attributes['room_id'])
                : $appointment->roomId,
            'scheduled_start_at' => array_key_exists('scheduled_start_at', $attributes)
                ? CarbonImmutable::parse($this->requiredTrimmedString($attributes['scheduled_start_at']))
                : $appointment->scheduledStartAt,
            'scheduled_end_at' => array_key_exists('scheduled_end_at', $attributes)
                ? CarbonImmutable::parse($this->requiredTrimmedString($attributes['scheduled_end_at']))
                : $appointment->scheduledEndAt,
            'timezone' => array_key_exists('timezone', $attributes)
                ? $this->requiredTrimmedString($attributes['timezone'])
                : $appointment->timezone,
        ];

        $this->assertCandidate($appointment->tenantId, $candidate);

        $updates = [];

        if ($candidate['patient_id'] !== $appointment->patientId) {
            $updates['patient_id'] = $candidate['patient_id'];
        }

        if ($candidate['provider_id'] !== $appointment->providerId) {
            $updates['provider_id'] = $candidate['provider_id'];
        }

        if ($candidate['clinic_id'] !== $appointment->clinicId) {
            $updates['clinic_id'] = $candidate['clinic_id'];
        }

        if ($candidate['room_id'] !== $appointment->roomId) {
            $updates['room_id'] = $candidate['room_id'];
        }

        if (! $candidate['scheduled_start_at']->equalTo($appointment->scheduledStartAt)) {
            $updates['scheduled_start_at'] = $candidate['scheduled_start_at'];
        }

        if (! $candidate['scheduled_end_at']->equalTo($appointment->scheduledEndAt)) {
            $updates['scheduled_end_at'] = $candidate['scheduled_end_at'];
        }

        if ($candidate['timezone'] !== $appointment->timezone) {
            $updates['timezone'] = $candidate['timezone'];
        }

        return $updates;
    }

    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     scheduled_start_at: CarbonImmutable,
     *     scheduled_end_at: CarbonImmutable,
     *     timezone: string
     * }  $candidate
     */
    private function assertCandidate(string $tenantId, array $candidate): void
    {
        if (! $this->patientRepository->findInTenant($tenantId, $candidate['patient_id'])) {
            throw new UnprocessableEntityHttpException('The patient_id field must reference an active patient in the current tenant.');
        }

        $provider = $this->providerRepository->findInTenant($tenantId, $candidate['provider_id']);

        if ($provider === null) {
            throw new UnprocessableEntityHttpException('The provider_id field must reference an active provider in the current tenant.');
        }

        if ($candidate['scheduled_end_at']->lessThanOrEqualTo($candidate['scheduled_start_at'])) {
            throw new UnprocessableEntityHttpException('Appointment scheduled_end_at must be later than scheduled_start_at.');
        }

        if ($candidate['clinic_id'] !== null && ! $this->clinicRepository->findClinic($tenantId, $candidate['clinic_id'])) {
            throw new UnprocessableEntityHttpException('The clinic_id field must reference an existing clinic in the current tenant.');
        }

        if ($candidate['clinic_id'] !== null && $provider->clinicId !== null && $candidate['clinic_id'] !== $provider->clinicId) {
            throw new UnprocessableEntityHttpException('The clinic_id field must match the provider clinic assignment when the provider is already assigned to a clinic.');
        }

        if ($candidate['room_id'] === null) {
            return;
        }

        if ($candidate['clinic_id'] === null) {
            throw new UnprocessableEntityHttpException('The room_id field requires clinic_id.');
        }

        if (! $this->clinicRepository->findRoom($tenantId, $candidate['clinic_id'], $candidate['room_id'])) {
            throw new UnprocessableEntityHttpException('The room_id field must reference an existing room in the selected clinic and tenant.');
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
