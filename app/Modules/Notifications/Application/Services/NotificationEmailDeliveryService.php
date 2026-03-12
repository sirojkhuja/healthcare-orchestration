<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\EmailEventRepository;
use App\Modules\Notifications\Application\Contracts\NotificationRepository;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Exceptions\EmailGatewayException;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class NotificationEmailDeliveryService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EmailGatewaySendService $emailGatewaySendService,
        private readonly EmailEventRepository $emailEventRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly NotificationOutboxPublisher $notificationOutboxPublisher,
    ) {}

    public function deliver(string $tenantId, string $notificationId): NotificationData
    {
        /** @var NotificationData $result */
        $result = DB::transaction(function () use ($tenantId, $notificationId): NotificationData {
            $notification = $this->notificationRepository->findForUpdate($tenantId, $notificationId);

            if (! $notification instanceof NotificationData) {
                throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
            }

            if ($notification->channel !== NotificationTemplateChannel::EMAIL->value) {
                return $notification;
            }

            if ($notification->status !== NotificationStatus::QUEUED->value) {
                return $notification;
            }

            try {
                $send = $this->emailGatewaySendService->send(
                    tenantId: $tenantId,
                    recipient: $notification->recipient,
                    subject: $this->subject($notification),
                    body: $notification->renderedBody,
                    metadata: $notification->metadata,
                    notificationId: $notification->notificationId,
                );
                $result = $send['result'];
                $updated = $this->notificationRepository->update($tenantId, $notification->notificationId, [
                    'status' => NotificationStatus::SENT->value,
                    'attempts' => $notification->attempts + 1,
                    'provider_key' => $result->providerKey,
                    'provider_message_id' => $result->messageId,
                    'last_error_code' => null,
                    'last_error_message' => null,
                    'sent_at' => $result->occurredAt,
                    'failed_at' => null,
                    'last_attempt_at' => $result->occurredAt,
                ]);

                if (! $updated instanceof NotificationData) {
                    throw new LogicException('Delivered email notification could not be reloaded.');
                }

                $event = $this->emailEventRepository->record($tenantId, [
                    'notification_id' => $notification->notificationId,
                    'source' => 'notification',
                    'event_type' => 'sent',
                    'recipient_email' => $result->recipientEmail,
                    'recipient_name' => $result->recipientName,
                    'subject' => $result->subject,
                    'provider_key' => $result->providerKey,
                    'message_id' => $result->messageId,
                    'error_code' => null,
                    'error_message' => null,
                    'metadata' => [
                        ...$notification->metadata,
                        'delivery' => $result->toArray(),
                    ],
                    'occurred_at' => $result->occurredAt,
                ]);

                $this->auditTrailWriter->record(new AuditRecordInput(
                    action: 'notifications.sent',
                    objectType: 'notification',
                    objectId: $updated->notificationId,
                    before: $notification->toArray(),
                    after: $updated->toArray(),
                    metadata: [
                        'delivery' => $result->toArray(),
                        'email_event_id' => $event->eventId,
                    ],
                ));
                $this->notificationOutboxPublisher->publishNotificationEvent('notification.sent', $updated, [
                    'delivery' => $result->toArray(),
                    'email_event_id' => $event->eventId,
                ]);

                return $updated;
            } catch (Throwable $exception) {
                return $this->markFailed(
                    notification: $notification,
                    errorCode: $exception instanceof EmailGatewayException ? $exception->errorCode() : 'email_provider_error',
                    errorMessage: $exception->getMessage(),
                    attemptsUsed: $exception instanceof EmailGatewayException && $exception->errorCode() === 'email_disabled' ? 0 : 1,
                );
            }
        });

        return $result;
    }

    private function markFailed(
        NotificationData $notification,
        ?string $errorCode,
        ?string $errorMessage,
        int $attemptsUsed,
    ): NotificationData {
        $attemptTime = CarbonImmutable::now();
        $updated = $this->notificationRepository->update($notification->tenantId, $notification->notificationId, [
            'status' => NotificationStatus::FAILED->value,
            'attempts' => $notification->attempts + $attemptsUsed,
            'provider_key' => config()->string('notifications.email.provider_key', 'email'),
            'provider_message_id' => null,
            'last_error_code' => $errorCode,
            'last_error_message' => $errorMessage,
            'failed_at' => $attemptTime,
            'last_attempt_at' => $attemptsUsed > 0 ? $attemptTime : $notification->lastAttemptAt,
        ]);

        if (! $updated instanceof NotificationData) {
            throw new LogicException('Failed email notification could not be reloaded.');
        }

        $event = $this->emailEventRepository->record($notification->tenantId, [
            'notification_id' => $notification->notificationId,
            'source' => 'notification',
            'event_type' => 'failed',
            'recipient_email' => is_string($notification->recipient['email'] ?? null) ? $notification->recipient['email'] : '',
            'recipient_name' => is_string($notification->recipient['name'] ?? null) ? $notification->recipient['name'] : null,
            'subject' => $this->subject($notification),
            'provider_key' => config()->string('notifications.email.provider_key', 'email'),
            'message_id' => null,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'metadata' => [
                ...$notification->metadata,
                'delivery' => [
                    'provider' => ['key' => config()->string('notifications.email.provider_key', 'email')],
                    'status' => 'failed',
                    'error' => [
                        'code' => $errorCode,
                        'message' => $errorMessage,
                    ],
                ],
            ],
            'occurred_at' => $attemptTime,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notifications.failed',
            objectType: 'notification',
            objectId: $updated->notificationId,
            before: $notification->toArray(),
            after: $updated->toArray(),
            metadata: [
                'delivery' => [
                    'provider' => ['key' => config()->string('notifications.email.provider_key', 'email')],
                    'status' => 'failed',
                    'error' => [
                        'code' => $errorCode,
                        'message' => $errorMessage,
                    ],
                ],
                'email_event_id' => $event->eventId,
            ],
        ));
        $this->notificationOutboxPublisher->publishNotificationEvent('notification.failed', $updated, [
            'delivery' => [
                'provider' => ['key' => config()->string('notifications.email.provider_key', 'email')],
                'status' => 'failed',
                'error' => [
                    'code' => $errorCode,
                    'message' => $errorMessage,
                ],
            ],
            'email_event_id' => $event->eventId,
        ]);

        return $updated;
    }

    private function subject(NotificationData $notification): string
    {
        if ($notification->renderedSubject === null || trim($notification->renderedSubject) === '') {
            throw new LogicException('Email notifications must provide a rendered_subject value.');
        }

        return trim($notification->renderedSubject);
    }
}
