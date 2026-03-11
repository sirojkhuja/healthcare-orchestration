<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\NotificationQueueGateway;
use App\Modules\Notifications\Application\Contracts\NotificationRepository;
use App\Modules\Notifications\Application\Contracts\NotificationTemplateRenderer;
use App\Modules\Notifications\Application\Contracts\NotificationTemplateRepository;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class NotificationDispatchService implements NotificationQueueGateway
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationTemplateRepository $notificationTemplateRepository,
        private readonly NotificationTemplateRenderer $notificationTemplateRenderer,
        private readonly NotificationRecipientNormalizer $notificationRecipientNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly NotificationOutboxPublisher $notificationOutboxPublisher,
        private readonly int $maxAttempts = 3,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[\Override]
    public function queue(array $attributes): NotificationData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $template = $this->templateOrFail($tenantId, $this->requiredString($attributes, 'template_id'));

        if (! $template->isActive) {
            throw new UnprocessableEntityHttpException('Only active templates may be used for notification sends.');
        }

        $variables = $this->requiredArray($attributes, 'variables');
        $recipientPayload = $this->requiredArray($attributes, 'recipient');
        $metadata = $this->optionalArray($attributes, 'metadata');
        $rendered = $this->notificationTemplateRenderer->render($template, $variables);
        $normalizedRecipient = $this->notificationRecipientNormalizer->normalize($template->channel, $recipientPayload);
        $queuedAt = CarbonImmutable::now();

        $notification = $this->notificationRepository->create($tenantId, [
            'template_id' => $template->templateId,
            'template_code' => $template->code,
            'template_version' => $template->currentVersion,
            'channel' => $template->channel,
            'recipient' => $normalizedRecipient['recipient'],
            'recipient_value' => $normalizedRecipient['recipient_value'],
            'rendered_subject' => $rendered->renderedSubject,
            'rendered_body' => $rendered->renderedBody,
            'variables' => $variables,
            'metadata' => $metadata,
            'status' => NotificationStatus::QUEUED->value,
            'attempts' => 0,
            'max_attempts' => $this->maxAttempts,
            'provider_key' => null,
            'provider_message_id' => null,
            'last_error_code' => null,
            'last_error_message' => null,
            'queued_at' => $queuedAt,
            'sent_at' => null,
            'failed_at' => null,
            'canceled_at' => null,
            'canceled_reason' => null,
            'last_attempt_at' => null,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notifications.queued',
            objectType: 'notification',
            objectId: $notification->notificationId,
            after: $notification->toArray(),
        ));
        $this->notificationOutboxPublisher->publishNotificationEvent('notification.queued', $notification);

        return $notification;
    }

    public function retry(string $notificationId): NotificationData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $notification = $this->notificationOrFail($tenantId, $notificationId);
        $status = NotificationStatus::from($notification->status);

        if (! $status->canRetry()) {
            throw new ConflictHttpException('Only failed notifications may be retried.');
        }

        if ($notification->attempts >= $notification->maxAttempts) {
            throw new ConflictHttpException('The notification has exhausted its retry budget.');
        }

        $updated = $this->notificationRepository->update($tenantId, $notificationId, [
            'status' => NotificationStatus::QUEUED->value,
            'queued_at' => CarbonImmutable::now(),
            'failed_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ]);

        if (! $updated instanceof NotificationData) {
            throw new LogicException('Retried notification could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notifications.retried',
            objectType: 'notification',
            objectId: $updated->notificationId,
            before: $notification->toArray(),
            after: $updated->toArray(),
        ));
        $this->notificationOutboxPublisher->publishNotificationEvent('notification.retried', $updated);

        return $updated;
    }

    public function cancel(string $notificationId, ?string $reason = null): NotificationData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $notification = $this->notificationOrFail($tenantId, $notificationId);
        $status = NotificationStatus::from($notification->status);

        if (! $status->canCancel()) {
            throw new ConflictHttpException('Only queued or failed notifications may be canceled.');
        }

        $updated = $this->notificationRepository->update($tenantId, $notificationId, [
            'status' => NotificationStatus::CANCELED->value,
            'canceled_at' => CarbonImmutable::now(),
            'canceled_reason' => $reason,
        ]);

        if (! $updated instanceof NotificationData) {
            throw new LogicException('Canceled notification could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notifications.canceled',
            objectType: 'notification',
            objectId: $updated->notificationId,
            before: $notification->toArray(),
            after: $updated->toArray(),
        ));
        $this->notificationOutboxPublisher->publishNotificationEvent('notification.canceled', $updated, [
            'reason' => $reason,
        ]);

        return $updated;
    }

    private function notificationOrFail(string $tenantId, string $notificationId): NotificationData
    {
        $notification = $this->notificationRepository->findInTenant($tenantId, $notificationId);

        if (! $notification instanceof NotificationData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $notification;
    }

    private function templateOrFail(string $tenantId, string $templateId): \App\Modules\Notifications\Application\Data\NotificationTemplateData
    {
        $template = $this->notificationTemplateRepository->findInTenant($tenantId, $templateId);

        if ($template === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function optionalArray(array $payload, string $key): array
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return [];
        }

        if (! is_array($payload[$key])) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be an object.', $key));
        }

        /** @var array<string, mixed> $value */
        $value = $payload[$key];

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requiredArray(array $payload, string $key): array
    {
        if (! array_key_exists($key, $payload) || ! is_array($payload[$key])) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be an object.', $key));
        }

        /** @var array<string, mixed> $value */
        $value = $payload[$key];

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        if (! array_key_exists($key, $payload) || ! is_string($payload[$key]) || trim($payload[$key]) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $key));
        }

        return trim($payload[$key]);
    }
}
