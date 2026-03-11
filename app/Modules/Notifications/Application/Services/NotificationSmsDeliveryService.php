<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\NotificationRepository;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Data\SmsDeliveryRequestData;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NotificationSmsDeliveryService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly SmsRoutingService $smsRoutingService,
        private readonly SmsMessageTypeResolver $smsMessageTypeResolver,
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

            if ($notification->channel !== NotificationTemplateChannel::SMS->value) {
                return $notification;
            }

            if ($notification->status !== NotificationStatus::QUEUED->value) {
                return $notification;
            }

            $remainingAttempts = max(0, $notification->maxAttempts - $notification->attempts);

            if ($remainingAttempts === 0) {
                return $this->markFailed(
                    notification: $notification,
                    errorCode: 'attempt_budget_exhausted',
                    errorMessage: 'The notification has exhausted its delivery budget.',
                    attemptsUsed: 0,
                    providerKey: $notification->providerKey,
                    attemptTime: CarbonImmutable::now(),
                    deliveryAttempts: [],
                );
            }

            $messageType = $this->smsMessageTypeResolver->resolve($notification->metadata, $notification->templateCode);
            $result = $this->smsRoutingService->send(
                new SmsDeliveryRequestData(
                    tenantId: $tenantId,
                    phoneNumber: $this->phoneNumber($notification),
                    message: $notification->renderedBody,
                    messageType: $messageType,
                    metadata: $notification->metadata,
                    notificationId: $notification->notificationId,
                ),
                attemptBudget: $remainingAttempts,
            );

            if ($result->successful) {
                $updated = $this->notificationRepository->update($tenantId, $notification->notificationId, [
                    'status' => NotificationStatus::SENT->value,
                    'attempts' => $notification->attempts + $result->attemptedCount(),
                    'provider_key' => $result->providerKey,
                    'provider_message_id' => $result->providerMessageId,
                    'last_error_code' => null,
                    'last_error_message' => null,
                    'sent_at' => $result->completedAt,
                    'failed_at' => null,
                    'last_attempt_at' => $result->completedAt,
                ]);

                if (! $updated instanceof NotificationData) {
                    throw new LogicException('Delivered notification could not be reloaded.');
                }

                $this->auditTrailWriter->record(new AuditRecordInput(
                    action: 'notifications.sent',
                    objectType: 'notification',
                    objectId: $updated->notificationId,
                    before: $notification->toArray(),
                    after: $updated->toArray(),
                    metadata: [
                        'delivery_attempts' => $result->attemptsArray(),
                    ],
                ));
                $this->notificationOutboxPublisher->publishNotificationEvent('notification.sent', $updated, [
                    'delivery_attempts' => $result->attemptsArray(),
                ]);

                return $updated;
            }

            return $this->markFailed(
                notification: $notification,
                errorCode: $result->lastErrorCode,
                errorMessage: $result->lastErrorMessage,
                attemptsUsed: $result->attemptedCount(),
                providerKey: $result->providerKey,
                attemptTime: $result->completedAt ?? CarbonImmutable::now(),
                deliveryAttempts: $result->attemptsArray(),
            );
        });

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $deliveryAttempts
     */
    private function markFailed(
        NotificationData $notification,
        ?string $errorCode,
        ?string $errorMessage,
        int $attemptsUsed,
        ?string $providerKey,
        CarbonImmutable $attemptTime,
        array $deliveryAttempts,
    ): NotificationData {
        $updated = $this->notificationRepository->update($notification->tenantId, $notification->notificationId, [
            'status' => NotificationStatus::FAILED->value,
            'attempts' => $notification->attempts + $attemptsUsed,
            'provider_key' => $providerKey,
            'provider_message_id' => null,
            'last_error_code' => $errorCode,
            'last_error_message' => $errorMessage,
            'failed_at' => $attemptTime,
            'last_attempt_at' => $attemptTime,
        ]);

        if (! $updated instanceof NotificationData) {
            throw new LogicException('Failed notification could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notifications.failed',
            objectType: 'notification',
            objectId: $updated->notificationId,
            before: $notification->toArray(),
            after: $updated->toArray(),
            metadata: [
                'delivery_attempts' => $deliveryAttempts,
            ],
        ));
        $this->notificationOutboxPublisher->publishNotificationEvent('notification.failed', $updated, [
            'delivery_attempts' => $deliveryAttempts,
        ]);

        return $updated;
    }

    private function phoneNumber(NotificationData $notification): string
    {
        $phoneNumber = $notification->recipient['phone_number'] ?? null;

        if (! is_string($phoneNumber) || trim($phoneNumber) === '') {
            throw new LogicException('SMS notification recipients must provide recipient.phone_number.');
        }

        return trim($phoneNumber);
    }
}
