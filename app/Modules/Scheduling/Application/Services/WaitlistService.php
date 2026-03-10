<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Modules\Scheduling\Application\Contracts\WaitlistRepository;
use App\Modules\Scheduling\Application\Data\WaitlistEntryData;
use App\Modules\Scheduling\Application\Data\WaitlistOfferData;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class WaitlistService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly WaitlistRepository $waitlistRepository,
        private readonly PatientRepository $patientRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly ClinicRepository $clinicRepository,
        private readonly AppointmentAttributeNormalizer $appointmentAttributeNormalizer,
        private readonly AppointmentActorContext $appointmentActorContext,
        private readonly ScheduledAppointmentCreator $scheduledAppointmentCreator,
        private readonly AvailabilitySlotService $availabilitySlotService,
        private readonly AvailabilityCacheInvalidator $availabilityCacheInvalidator,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): WaitlistEntryData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->normalizeCreateAttributes($tenantId, $attributes);
        $entry = $this->waitlistRepository->create($tenantId, [
            ...$normalized,
            'status' => 'open',
            'booked_appointment_id' => null,
            'offered_slot' => null,
        ]);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.waitlist_added',
            objectType: 'appointment_waitlist_entry',
            objectId: $entry->entryId,
            after: $entry->toArray(),
        ));

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<WaitlistEntryData>
     */
    public function list(array $filters = []): array
    {
        return $this->waitlistRepository->listForTenant($this->tenantContext->requireTenantId(), $filters);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function offer(string $entryId, array $attributes): WaitlistOfferData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $entry = $this->entryOrFail($tenantId, $entryId);
        $this->assertOpen($entry, 'booked');
        $normalizedAppointment = $this->normalizeOfferedAppointment($tenantId, $entry, $attributes);
        $this->assertDesiredDateRange($entry, $normalizedAppointment);
        $this->assertPreferredWindow($entry, $normalizedAppointment);
        $this->assertSlotAvailable(
            providerId: $entry->providerId,
            startAt: $normalizedAppointment['scheduled_start_at'],
            endAt: $normalizedAppointment['scheduled_end_at'],
            timezone: $normalizedAppointment['timezone'],
        );
        $actor = $this->appointmentActorContext->current();
        $occurredAt = CarbonImmutable::now();

        /** @var WaitlistOfferData $result */
        $result = DB::transaction(function () use ($tenantId, $entry, $normalizedAppointment, $actor, $occurredAt): WaitlistOfferData {
            $appointment = $this->scheduledAppointmentCreator->create(
                tenantId: $tenantId,
                patientId: $entry->patientId,
                providerId: $entry->providerId,
                clinicId: $normalizedAppointment['clinic_id'],
                roomId: $normalizedAppointment['room_id'],
                scheduledStartAt: $normalizedAppointment['scheduled_start_at'],
                scheduledEndAt: $normalizedAppointment['scheduled_end_at'],
                timezone: $normalizedAppointment['timezone'],
                actor: $actor,
                occurredAt: $occurredAt,
            );
            $updatedEntry = $this->waitlistRepository->update($tenantId, $entry->entryId, [
                'status' => 'booked',
                'booked_appointment_id' => $appointment->appointmentId,
                'offered_slot' => [
                    'start_at' => $appointment->scheduledStartAt->toIso8601String(),
                    'end_at' => $appointment->scheduledEndAt->toIso8601String(),
                    'timezone' => $appointment->timezone,
                ],
            ]);

            if (! $updatedEntry instanceof WaitlistEntryData) {
                throw new LogicException('Booked waitlist entry could not be reloaded.');
            }

            $offer = new WaitlistOfferData($updatedEntry, $appointment);
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'appointments.waitlist_booked',
                objectType: 'appointment_waitlist_entry',
                objectId: $updatedEntry->entryId,
                before: $entry->toArray(),
                after: $offer->toArray(),
            ));

            return $offer;
        });

        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return $result;
    }

    public function remove(string $entryId): WaitlistEntryData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $entry = $this->entryOrFail($tenantId, $entryId);
        $this->assertOpen($entry, 'removed');
        $updated = $this->waitlistRepository->update($tenantId, $entry->entryId, [
            'status' => 'removed',
        ]);

        if (! $updated instanceof WaitlistEntryData) {
            throw new LogicException('Removed waitlist entry could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.waitlist_removed',
            objectType: 'appointment_waitlist_entry',
            objectId: $updated->entryId,
            before: $entry->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
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
     * }  $appointment
     */
    private function assertDesiredDateRange(WaitlistEntryData $entry, array $appointment): void
    {
        $startDate = $appointment['scheduled_start_at']->setTimezone($appointment['timezone'])->toDateString();
        $endDate = $appointment['scheduled_end_at']->setTimezone($appointment['timezone'])->toDateString();

        if ($startDate < $entry->desiredDateFrom || $endDate > $entry->desiredDateTo) {
            throw new UnprocessableEntityHttpException('The offered slot must fall inside the waitlist desired date range.');
        }
    }

    private function assertOpen(WaitlistEntryData $entry, string $action): void
    {
        if ($entry->status !== 'open') {
            throw new ConflictHttpException(sprintf('Only open waitlist entries may be %s.', $action));
        }
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
     * }  $appointment
     */
    private function assertPreferredWindow(WaitlistEntryData $entry, array $appointment): void
    {
        if ($entry->preferredStartTime === null && $entry->preferredEndTime === null) {
            return;
        }

        $startTime = $appointment['scheduled_start_at']->setTimezone($appointment['timezone'])->format('H:i');
        $endTime = $appointment['scheduled_end_at']->setTimezone($appointment['timezone'])->format('H:i');

        if ($startTime < $entry->preferredStartTime || $endTime > $entry->preferredEndTime) {
            throw new UnprocessableEntityHttpException('The offered slot must fit within the waitlist preferred time window.');
        }
    }

    private function assertSlotAvailable(
        string $providerId,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $timezone,
    ): void {
        if (! $this->availabilitySlotService->isSlotAvailable($providerId, $startAt, $endAt, $timezone)) {
            throw new ConflictHttpException('The requested appointment slot is not currently available.');
        }
    }

    private function entryOrFail(string $tenantId, string $entryId): WaitlistEntryData
    {
        $entry = $this->waitlistRepository->findInTenant($tenantId, $entryId);

        if (! $entry instanceof WaitlistEntryData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     desired_date_from: string,
     *     desired_date_to: string,
     *     preferred_start_time: ?string,
     *     preferred_end_time: ?string,
     *     notes: ?string
     * }
     */
    private function normalizeCreateAttributes(string $tenantId, array $attributes): array
    {
        $patientId = $this->requiredString($attributes['patient_id'] ?? null, 'Waitlist patient_id is required.');
        $providerId = $this->requiredString($attributes['provider_id'] ?? null, 'Waitlist provider_id is required.');
        $clinicId = $this->nullableString($attributes['clinic_id'] ?? null);
        $roomId = $this->nullableString($attributes['room_id'] ?? null);
        $desiredDateFrom = $this->dateString($attributes['desired_date_from'] ?? null, 'Waitlist desired_date_from must use Y-m-d format.');
        $desiredDateTo = $this->dateString($attributes['desired_date_to'] ?? null, 'Waitlist desired_date_to must use Y-m-d format.');
        $preferredStartTime = $this->nullableString($attributes['preferred_start_time'] ?? null);
        $preferredEndTime = $this->nullableString($attributes['preferred_end_time'] ?? null);
        $notes = $this->nullableString($attributes['notes'] ?? null);

        if ($desiredDateTo < $desiredDateFrom) {
            throw new UnprocessableEntityHttpException('Waitlist desired_date_to must be on or after desired_date_from.');
        }

        if (($preferredStartTime === null) !== ($preferredEndTime === null)) {
            throw new UnprocessableEntityHttpException('Waitlist preferred start and end times must be provided together.');
        }

        if (! $this->patientRepository->findInTenant($tenantId, $patientId)) {
            throw new UnprocessableEntityHttpException('The patient_id field must reference an active patient in the current tenant.');
        }

        $provider = $this->providerRepository->findInTenant($tenantId, $providerId);

        if (! $provider instanceof ProviderData) {
            throw new UnprocessableEntityHttpException('The provider_id field must reference an active provider in the current tenant.');
        }

        $this->assertClinicAndRoom($tenantId, $provider, $clinicId, $roomId);

        return [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'clinic_id' => $clinicId,
            'room_id' => $roomId,
            'desired_date_from' => $desiredDateFrom,
            'desired_date_to' => $desiredDateTo,
            'preferred_start_time' => $preferredStartTime,
            'preferred_end_time' => $preferredEndTime,
            'notes' => $notes,
        ];
    }

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
    private function normalizeOfferedAppointment(string $tenantId, WaitlistEntryData $entry, array $attributes): array
    {
        return $this->appointmentAttributeNormalizer->normalizeCreate([
            'patient_id' => $entry->patientId,
            'provider_id' => $entry->providerId,
            'clinic_id' => $attributes['clinic_id'] ?? $entry->clinicId,
            'room_id' => array_key_exists('room_id', $attributes) ? $attributes['room_id'] : $entry->roomId,
            'scheduled_start_at' => $attributes['scheduled_start_at'] ?? null,
            'scheduled_end_at' => $attributes['scheduled_end_at'] ?? null,
            'timezone' => $attributes['timezone'] ?? null,
        ], $tenantId);
    }

    private function assertClinicAndRoom(string $tenantId, ProviderData $provider, ?string $clinicId, ?string $roomId): void
    {
        if ($clinicId !== null && ! $this->clinicRepository->findClinic($tenantId, $clinicId)) {
            throw new UnprocessableEntityHttpException('The clinic_id field must reference an existing clinic in the current tenant.');
        }

        if ($clinicId !== null && $provider->clinicId !== null && $clinicId !== $provider->clinicId) {
            throw new UnprocessableEntityHttpException('The clinic_id field must match the provider clinic assignment when the provider is already assigned to a clinic.');
        }

        if ($roomId === null) {
            return;
        }

        if ($clinicId === null) {
            throw new UnprocessableEntityHttpException('The room_id field requires clinic_id.');
        }

        if (! $this->clinicRepository->findRoom($tenantId, $clinicId, $roomId)) {
            throw new UnprocessableEntityHttpException('The room_id field must reference an existing room in the selected clinic and tenant.');
        }
    }

    private function dateString(mixed $value, string $message): string
    {
        $normalized = $this->requiredString($value, $message);
        $date = CarbonImmutable::createFromFormat('Y-m-d', $normalized, 'UTC');

        if (! $date instanceof CarbonImmutable || $date->toDateString() !== $normalized) {
            throw new UnprocessableEntityHttpException($message);
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function requiredString(mixed $value, string $message): string
    {
        return $this->nullableString($value) ?? throw new UnprocessableEntityHttpException($message);
    }
}
