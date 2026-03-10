<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use App\Modules\Scheduling\Application\Contracts\AppointmentParticipantRepository;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentParticipantData;
use App\Modules\Scheduling\Domain\Appointments\AppointmentParticipantType;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AppointmentParticipantService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentParticipantRepository $appointmentParticipantRepository,
        private readonly ManagedUserRepository $managedUserRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $appointmentId, array $attributes): AppointmentParticipantData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($tenantId, $appointmentId);
        $normalized = $this->normalizeCreateAttributes($tenantId, $attributes);
        $this->assertNoDuplicate(
            $this->appointmentParticipantRepository->listForAppointment($tenantId, $appointment->appointmentId),
            $normalized,
        );

        $participant = $this->appointmentParticipantRepository->create($tenantId, $appointment->appointmentId, $normalized);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.participant_added',
            objectType: 'appointment',
            objectId: $appointment->appointmentId,
            after: ['participant' => $participant->toArray()],
            metadata: [
                'appointment_id' => $appointment->appointmentId,
                'participant_id' => $participant->participantId,
            ],
        ));

        return $participant;
    }

    public function delete(string $appointmentId, string $participantId): AppointmentParticipantData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($tenantId, $appointmentId);
        $participant = $this->participantOrFail($tenantId, $appointment->appointmentId, $participantId);

        if (! $this->appointmentParticipantRepository->delete($tenantId, $appointment->appointmentId, $participant->participantId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.participant_removed',
            objectType: 'appointment',
            objectId: $appointment->appointmentId,
            before: ['participant' => $participant->toArray()],
            metadata: [
                'appointment_id' => $appointment->appointmentId,
                'participant_id' => $participant->participantId,
            ],
        ));

        return $participant;
    }

    /**
     * @return list<AppointmentParticipantData>
     */
    public function list(string $appointmentId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($tenantId, $appointmentId);

        return $this->appointmentParticipantRepository->listForAppointment($tenantId, $appointment->appointmentId);
    }

    private function appointmentOrFail(string $tenantId, string $appointmentId): AppointmentData
    {
        $appointment = $this->appointmentRepository->findInTenant($tenantId, $appointmentId);

        if (! $appointment instanceof AppointmentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $appointment;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     participant_type: string,
     *     reference_id: ?string,
     *     display_name: string,
     *     role: string,
     *     required: bool,
     *     notes: ?string
     * }
     */
    private function normalizeCreateAttributes(string $tenantId, array $attributes): array
    {
        $participantType = $this->participantType($attributes['participant_type'] ?? null);
        $role = $this->requiredString($attributes['role'] ?? null, 'Appointment participant role is required.');
        $notes = $this->nullableString($attributes['notes'] ?? null);
        $required = (bool) ($attributes['required'] ?? false);

        return match ($participantType) {
            AppointmentParticipantType::USER => $this->normalizeUserParticipant($tenantId, $role, $required, $notes, $attributes),
            AppointmentParticipantType::PROVIDER => $this->normalizeProviderParticipant($tenantId, $role, $required, $notes, $attributes),
            AppointmentParticipantType::EXTERNAL => $this->normalizeExternalParticipant($role, $required, $notes, $attributes),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     participant_type: string,
     *     reference_id: null,
     *     display_name: string,
     *     role: string,
     *     required: bool,
     *     notes: ?string
     * }
     */
    private function normalizeExternalParticipant(
        string $role,
        bool $required,
        ?string $notes,
        array $attributes,
    ): array {
        $displayName = $this->requiredString($attributes['display_name'] ?? null, 'External appointment participants require display_name.');

        if ($this->nullableString($attributes['reference_id'] ?? null) !== null) {
            throw new UnprocessableEntityHttpException('External appointment participants must not include reference_id.');
        }

        return [
            'participant_type' => AppointmentParticipantType::EXTERNAL->value,
            'reference_id' => null,
            'display_name' => $displayName,
            'role' => $role,
            'required' => $required,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     participant_type: string,
     *     reference_id: string,
     *     display_name: string,
     *     role: string,
     *     required: bool,
     *     notes: ?string
     * }
     */
    private function normalizeProviderParticipant(
        string $tenantId,
        string $role,
        bool $required,
        ?string $notes,
        array $attributes,
    ): array {
        $referenceId = $this->requiredString($attributes['reference_id'] ?? null, 'Provider appointment participants require reference_id.');
        $provider = $this->providerRepository->findInTenant($tenantId, $referenceId);

        if (! $provider instanceof ProviderData) {
            throw new UnprocessableEntityHttpException('The reference_id field must resolve to an active tenant provider.');
        }

        return [
            'participant_type' => AppointmentParticipantType::PROVIDER->value,
            'reference_id' => $referenceId,
            'display_name' => $this->providerDisplayName($provider),
            'role' => $role,
            'required' => $required,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     participant_type: string,
     *     reference_id: string,
     *     display_name: string,
     *     role: string,
     *     required: bool,
     *     notes: ?string
     * }
     */
    private function normalizeUserParticipant(
        string $tenantId,
        string $role,
        bool $required,
        ?string $notes,
        array $attributes,
    ): array {
        $referenceId = $this->requiredString($attributes['reference_id'] ?? null, 'User appointment participants require reference_id.');
        $user = $this->managedUserRepository->findInTenant($referenceId, $tenantId);

        if ($user === null || $user->status !== 'active') {
            throw new UnprocessableEntityHttpException('The reference_id field must resolve to an active tenant user membership.');
        }

        return [
            'participant_type' => AppointmentParticipantType::USER->value,
            'reference_id' => $referenceId,
            'display_name' => $user->name,
            'role' => $role,
            'required' => $required,
            'notes' => $notes,
        ];
    }

    /**
     * @param  list<AppointmentParticipantData>  $participants
     * @param  array{
     *     participant_type: string,
     *     reference_id: ?string,
     *     display_name: string,
     *     role: string,
     *     required: bool,
     *     notes: ?string
     * }  $candidate
     */
    private function assertNoDuplicate(array $participants, array $candidate): void
    {
        foreach ($participants as $participant) {
            if (
                $participant->participantType !== AppointmentParticipantType::EXTERNAL->value
                && $participant->participantType === $candidate['participant_type']
                && $participant->referenceId === $candidate['reference_id']
            ) {
                throw new ConflictHttpException('The appointment already contains that participant.');
            }

            if (
                $participant->participantType === AppointmentParticipantType::EXTERNAL->value
                && $candidate['participant_type'] === AppointmentParticipantType::EXTERNAL->value
                && $this->normalizedCompareString($participant->displayName) === $this->normalizedCompareString($candidate['display_name'])
                && $this->normalizedCompareString($participant->role) === $this->normalizedCompareString($candidate['role'])
            ) {
                throw new ConflictHttpException('The appointment already contains that participant.');
            }
        }
    }

    private function participantOrFail(string $tenantId, string $appointmentId, string $participantId): AppointmentParticipantData
    {
        $participant = $this->appointmentParticipantRepository->findInTenant($tenantId, $appointmentId, $participantId);

        if (! $participant instanceof AppointmentParticipantData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $participant;
    }

    private function participantType(mixed $value): AppointmentParticipantType
    {
        $normalized = $this->nullableString($value);

        return AppointmentParticipantType::tryFrom($normalized ?? '')
            ?? throw new UnprocessableEntityHttpException('Appointment participant type is invalid.');
    }

    private function providerDisplayName(ProviderData $provider): string
    {
        $parts = array_values(array_filter([
            $provider->preferredName ?? $provider->firstName,
            $provider->lastName,
        ]));

        return $parts !== [] ? implode(' ', $parts) : $provider->providerId;
    }

    private function normalizedCompareString(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim($value)));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredString(mixed $value, string $message): string
    {
        return $this->nullableString($value) ?? throw new UnprocessableEntityHttpException($message);
    }
}
