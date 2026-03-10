<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\Scheduling\Application\Contracts\AppointmentNoteRepository;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentNoteData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AppointmentNoteService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentNoteRepository $appointmentNoteRepository,
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $appointmentId, array $attributes): AppointmentNoteData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($tenantId, $appointmentId);
        $current = $this->authenticatedRequestContext->current();
        $note = $this->appointmentNoteRepository->create($tenantId, $appointment->appointmentId, [
            'body' => $this->requiredBody($attributes['body'] ?? null),
            'author_user_id' => $current->user->id,
            'author_name' => $current->user->name,
            'author_email' => $current->user->email,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.note_added',
            objectType: 'appointment',
            objectId: $appointment->appointmentId,
            after: ['note' => $note->toArray()],
            metadata: [
                'appointment_id' => $appointment->appointmentId,
                'note_id' => $note->noteId,
            ],
        ));

        return $note;
    }

    public function delete(string $appointmentId, string $noteId): AppointmentNoteData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($tenantId, $appointmentId);
        $note = $this->noteOrFail($tenantId, $appointment->appointmentId, $noteId);

        if (! $this->appointmentNoteRepository->delete($tenantId, $appointment->appointmentId, $note->noteId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.note_deleted',
            objectType: 'appointment',
            objectId: $appointment->appointmentId,
            before: ['note' => $note->toArray()],
            metadata: [
                'appointment_id' => $appointment->appointmentId,
                'note_id' => $note->noteId,
            ],
        ));

        return $note;
    }

    /**
     * @return list<AppointmentNoteData>
     */
    public function list(string $appointmentId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($tenantId, $appointmentId);

        return $this->appointmentNoteRepository->listForAppointment($tenantId, $appointment->appointmentId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $appointmentId, string $noteId, array $attributes): AppointmentNoteData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentOrFail($tenantId, $appointmentId);
        $note = $this->noteOrFail($tenantId, $appointment->appointmentId, $noteId);
        $body = $this->requiredBody($attributes['body'] ?? null);

        if ($body === $note->body) {
            return $note;
        }

        $updated = $this->appointmentNoteRepository->update($tenantId, $appointment->appointmentId, $note->noteId, [
            'body' => $body,
        ]);

        if (! $updated instanceof AppointmentNoteData) {
            throw new \LogicException('Updated appointment note could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.note_updated',
            objectType: 'appointment',
            objectId: $appointment->appointmentId,
            before: ['note' => $note->toArray()],
            after: ['note' => $updated->toArray()],
            metadata: [
                'appointment_id' => $appointment->appointmentId,
                'note_id' => $note->noteId,
            ],
        ));

        return $updated;
    }

    private function appointmentOrFail(string $tenantId, string $appointmentId): AppointmentData
    {
        $appointment = $this->appointmentRepository->findInTenant($tenantId, $appointmentId);

        if (! $appointment instanceof AppointmentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $appointment;
    }

    private function noteOrFail(string $tenantId, string $appointmentId, string $noteId): AppointmentNoteData
    {
        $note = $this->appointmentNoteRepository->findInTenant($tenantId, $appointmentId, $noteId);

        if (! $note instanceof AppointmentNoteData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $note;
    }

    private function requiredBody(mixed $value): string
    {
        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException('Appointment note body is required.');
        }

        $normalized = trim($value);

        if ($normalized === '') {
            throw new UnprocessableEntityHttpException('Appointment note body is required.');
        }

        return $normalized;
    }
}
