<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\NotificationRepository;
use App\Modules\Notifications\Application\Contracts\TelegramBotGateway;
use App\Modules\Notifications\Application\Contracts\TelegramProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Data\TelegramSendRequestData;
use App\Modules\Notifications\Application\Exceptions\TelegramGatewayException;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class NotificationTelegramDeliveryService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly TelegramProviderSettingsRepository $telegramProviderSettingsRepository,
        private readonly TelegramParseModeResolver $telegramParseModeResolver,
        private readonly TelegramBotGateway $telegramBotGateway,
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

            if ($notification->channel !== NotificationTemplateChannel::TELEGRAM->value) {
                return $notification;
            }

            if ($notification->status !== NotificationStatus::QUEUED->value) {
                return $notification;
            }

            $settings = $this->telegramProviderSettingsRepository->get($tenantId);

            if (! $settings->enabled) {
                return $this->markFailed(
                    notification: $notification,
                    errorCode: 'telegram_disabled',
                    errorMessage: 'The Telegram provider is disabled for the current tenant.',
                    attemptsUsed: 0,
                );
            }

            $parseMode = $this->telegramParseModeResolver->resolveFromMetadata($settings, $notification->metadata);

            try {
                $result = $this->telegramBotGateway->sendMessage(new TelegramSendRequestData(
                    tenantId: $tenantId,
                    chatId: $this->chatId($notification),
                    message: $notification->renderedBody,
                    parseMode: $parseMode,
                    metadata: $notification->metadata,
                    notificationId: $notification->notificationId,
                ));
            } catch (Throwable $exception) {
                return $this->markFailed(
                    notification: $notification,
                    errorCode: $exception instanceof TelegramGatewayException ? $exception->errorCode() : 'telegram_provider_error',
                    errorMessage: $exception->getMessage(),
                    attemptsUsed: 1,
                );
            }

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
                throw new LogicException('Delivered Telegram notification could not be reloaded.');
            }

            $delivery = $result->toArray();
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'notifications.sent',
                objectType: 'notification',
                objectId: $updated->notificationId,
                before: $notification->toArray(),
                after: $updated->toArray(),
                metadata: ['delivery' => $delivery],
            ));
            $this->notificationOutboxPublisher->publishNotificationEvent('notification.sent', $updated, [
                'delivery' => $delivery,
            ]);

            return $updated;
        });

        return $result;
    }

    private function chatId(NotificationData $notification): string
    {
        $chatId = $notification->recipient['chat_id'] ?? null;

        if (! is_string($chatId) || trim($chatId) === '') {
            throw new LogicException('Telegram notification recipients must provide recipient.chat_id.');
        }

        return trim($chatId);
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
            'provider_key' => $this->telegramBotGateway->providerKey(),
            'provider_message_id' => null,
            'last_error_code' => $errorCode,
            'last_error_message' => $errorMessage,
            'failed_at' => $attemptTime,
            'last_attempt_at' => $attemptTime,
        ]);

        if (! $updated instanceof NotificationData) {
            throw new LogicException('Failed Telegram notification could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notifications.failed',
            objectType: 'notification',
            objectId: $updated->notificationId,
            before: $notification->toArray(),
            after: $updated->toArray(),
            metadata: [
                'delivery' => [
                    'provider' => ['key' => $this->telegramBotGateway->providerKey()],
                    'status' => 'failed',
                    'error' => [
                        'code' => $errorCode,
                        'message' => $errorMessage,
                    ],
                ],
            ],
        ));
        $this->notificationOutboxPublisher->publishNotificationEvent('notification.failed', $updated, [
            'delivery' => [
                'provider' => ['key' => $this->telegramBotGateway->providerKey()],
                'status' => 'failed',
                'error' => [
                    'code' => $errorCode,
                    'message' => $errorMessage,
                ],
            ],
        ]);

        return $updated;
    }
}
