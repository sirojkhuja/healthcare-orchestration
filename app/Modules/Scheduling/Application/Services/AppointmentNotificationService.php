<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\NotificationQueueGateway;
use App\Modules\Notifications\Application\Contracts\NotificationRepository;
use App\Modules\Notifications\Application\Contracts\NotificationTemplateRepository;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Patient\Application\Contracts\PatientContactRepository;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Scheduling\Application\Contracts\AppointmentNotificationRepository;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentNotificationDispatchData;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AppointmentNotificationService
{
    /**
     * @var array<string, array<string, string>>
     */
    private const TEMPLATE_CODES = [
        'reminder' => [
            'sms' => 'APPOINTMENT-REMINDER-SMS',
            'email' => 'APPOINTMENT-REMINDER-EMAIL',
        ],
        'confirmation' => [
            'sms' => 'APPOINTMENT-CONFIRMATION-SMS',
            'email' => 'APPOINTMENT-CONFIRMATION-EMAIL',
        ],
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AppointmentNotificationRepository $appointmentNotificationRepository,
        private readonly PatientRepository $patientRepository,
        private readonly PatientContactRepository $patientContactRepository,
        private readonly ClinicRepository $clinicRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationQueueGateway $notificationQueueGateway,
        private readonly NotificationTemplateRepository $notificationTemplateRepository,
        private readonly AppointmentNotificationRecipientResolver $recipientResolver,
        private readonly AppointmentReminderWindowResolver $windowResolver,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function sendConfirmation(string $appointmentId): AppointmentNotificationDispatchData
    {
        return $this->dispatch($appointmentId, 'confirmation');
    }

    public function sendReminder(string $appointmentId): AppointmentNotificationDispatchData
    {
        return $this->dispatch($appointmentId, 'reminder');
    }

    private function appointmentOrFail(string $tenantId, string $appointmentId): AppointmentData
    {
        $appointment = $this->appointmentRepository->findInTenant($tenantId, $appointmentId);

        if (! $appointment instanceof AppointmentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $appointment;
    }

    private function assertAllowed(string $type, AppointmentData $appointment): void
    {
        $now = CarbonImmutable::now($appointment->timezone);

        if ($appointment->scheduledStartAt->lessThanOrEqualTo($now)) {
            throw new ConflictHttpException('Only future appointments may dispatch reminders or confirmations.');
        }

        if ($type === 'reminder' && ! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            throw new ConflictHttpException('Only scheduled or confirmed appointments may dispatch reminders.');
        }

        if ($type === 'confirmation' && $appointment->status !== 'scheduled') {
            throw new ConflictHttpException('Only scheduled appointments may dispatch confirmation requests.');
        }
    }

    private function assertConfirmationEnabled(string $tenantId, AppointmentData $appointment): void
    {
        if ($appointment->clinicId === null) {
            throw new UnprocessableEntityHttpException('Appointment confirmation requests require a clinic-linked appointment.');
        }

        if ($this->clinicRepository->findClinic($tenantId, $appointment->clinicId) === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $settings = $this->clinicRepository->settings($tenantId, $appointment->clinicId);

        if (! $settings->requireAppointmentConfirmation) {
            throw new UnprocessableEntityHttpException('Appointment confirmation requests are disabled for the linked clinic.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVariables(AppointmentData $appointment, PatientData $patient, ?string $windowKey): array
    {
        $displayName = $patient->preferredName !== null && trim($patient->preferredName) !== ''
            ? trim($patient->preferredName)
            : trim($patient->firstName.' '.$patient->lastName);

        return [
            'patient' => [
                'id' => $patient->patientId,
                'first_name' => $patient->firstName,
                'last_name' => $patient->lastName,
                'preferred_name' => $patient->preferredName,
                'full_name' => $displayName !== '' ? $displayName : $patient->patientId,
            ],
            'provider' => [
                'id' => $appointment->providerId,
                'name' => $appointment->providerDisplayName,
            ],
            'clinic' => [
                'id' => $appointment->clinicId,
                'name' => $appointment->clinicName,
            ],
            'appointment' => [
                'id' => $appointment->appointmentId,
                'status' => $appointment->status,
                'start_at' => $appointment->scheduledStartAt->toIso8601String(),
                'end_at' => $appointment->scheduledEndAt->toIso8601String(),
                'timezone' => $appointment->timezone,
                'window_key' => $windowKey,
            ],
        ];
    }

    private function dispatch(string $appointmentId, string $type): AppointmentNotificationDispatchData
    {
        $tenantId = $this->tenantContext->requireTenantId();

        /** @var AppointmentNotificationDispatchData $result */
        $result = DB::transaction(function () use ($tenantId, $appointmentId, $type): AppointmentNotificationDispatchData {
            DB::table('appointments')
                ->where('tenant_id', $tenantId)
                ->where('id', $appointmentId)
                ->lockForUpdate()
                ->first();

            $appointment = $this->appointmentOrFail($tenantId, $appointmentId);
            $this->assertAllowed($type, $appointment);

            if ($type === 'confirmation') {
                $this->assertConfirmationEnabled($tenantId, $appointment);
            }

            $windowKey = $type === 'reminder' ? $this->windowResolver->resolve($appointment) : null;
            $patient = $this->patientRepository->findInTenant($tenantId, $appointment->patientId);

            if (! $patient instanceof PatientData) {
                throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
            }

            $contacts = $this->patientContactRepository->listForPatient($tenantId, $patient->patientId);
            $variables = $this->buildVariables($appointment, $patient, $windowKey);
            $notifications = [];

            foreach (self::TEMPLATE_CODES[$type] as $channel => $templateCode) {
                $template = $this->notificationTemplateRepository->findActiveByCode($tenantId, $templateCode);

                if ($template === null) {
                    continue;
                }

                $recipient = $this->recipientResolver->resolve($channel, $patient, $contacts);

                if ($recipient === null) {
                    continue;
                }

                $existingLink = $this->appointmentNotificationRepository->findReusableLink(
                    $tenantId,
                    $appointment->appointmentId,
                    $type,
                    $channel,
                    $windowKey,
                );

                if ($existingLink !== null) {
                    $notifications[] = $this->notificationOrFail($tenantId, $existingLink->notificationId);

                    continue;
                }

                $notification = $this->notificationQueueGateway->queue([
                    'template_id' => $template->templateId,
                    'recipient' => $recipient,
                    'variables' => $variables,
                    'metadata' => [
                        'object_type' => 'appointment',
                        'object_id' => $appointment->appointmentId,
                        'appointment_id' => $appointment->appointmentId,
                        'patient_id' => $appointment->patientId,
                        'provider_id' => $appointment->providerId,
                        'clinic_id' => $appointment->clinicId,
                        'notification_type' => $type,
                        'window_key' => $windowKey,
                    ],
                ]);

                $this->appointmentNotificationRepository->create($tenantId, [
                    'appointment_id' => $appointment->appointmentId,
                    'notification_id' => $notification->notificationId,
                    'notification_type' => $type,
                    'channel' => $notification->channel,
                    'template_id' => $notification->templateId,
                    'template_code' => $notification->templateCode,
                    'recipient_value' => $notification->recipientValue,
                    'window_key' => $windowKey,
                    'requested_at' => CarbonImmutable::now(),
                ]);
                $notifications[] = $notification;
            }

            if ($notifications === []) {
                throw new UnprocessableEntityHttpException(
                    'No active appointment notification template with a resolvable patient recipient exists in the current tenant.',
                );
            }

            $result = new AppointmentNotificationDispatchData($appointment, $type, $windowKey, $notifications);
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: $type === 'reminder' ? 'appointments.reminder_sent' : 'appointments.confirmation_sent',
                objectType: 'appointment',
                objectId: $appointment->appointmentId,
                after: $result->toArray(),
                metadata: [
                    'notification_ids' => array_map(
                        static fn (NotificationData $notification): string => $notification->notificationId,
                        $notifications,
                    ),
                ],
            ));

            return $result;
        });

        return $result;
    }

    private function notificationOrFail(string $tenantId, string $notificationId): NotificationData
    {
        $notification = $this->notificationRepository->findInTenant($tenantId, $notificationId);

        if (! $notification instanceof NotificationData) {
            throw new LogicException('Linked appointment notification could not be reloaded.');
        }

        return $notification;
    }
}
