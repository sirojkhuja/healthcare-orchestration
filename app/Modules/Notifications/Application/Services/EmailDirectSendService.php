<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\EmailEventRepository;
use App\Modules\Notifications\Application\Data\EmailEventData;
use App\Modules\Notifications\Application\Data\EmailSendResultData;
use App\Modules\Notifications\Application\Exceptions\EmailGatewayException;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

final class EmailDirectSendService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EmailGatewaySendService $emailGatewaySendService,
        private readonly EmailEventRepository $emailEventRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function send(array $attributes): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $recipient = $this->requiredArray($attributes, 'recipient');
        $subject = $this->requiredString($attributes, 'subject');
        $body = $this->requiredString($attributes, 'body');
        $metadata = $this->optionalArray($attributes, 'metadata');

        try {
            $send = $this->emailGatewaySendService->send($tenantId, $recipient, $subject, $body, $metadata);
            $normalizedRecipient = $send['recipient'];
            $result = $send['result'];
        } catch (Throwable $exception) {
            $normalizedRecipient = $recipient;
            $result = new EmailSendResultData(
                providerKey: config()->string('notifications.email.provider_key', 'email'),
                recipientEmail: $this->recipientEmail($recipient),
                recipientName: $this->recipientName($recipient),
                subject: $subject,
                status: 'failed',
                occurredAt: CarbonImmutable::now(),
                errorCode: $exception instanceof EmailGatewayException ? $exception->errorCode() : 'email_provider_error',
                errorMessage: $exception->getMessage(),
            );
        }

        $event = $this->recordEvent($tenantId, $normalizedRecipient, $subject, $metadata, $result);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $result->status === 'sent' ? 'notifications.email_sent' : 'notifications.email_failed',
            objectType: 'email_event',
            objectId: $event->eventId,
            after: $event->toArray(),
        ));

        return [
            'status' => $result->status === 'sent' ? 'email_sent' : 'email_failed',
            'data' => [
                'recipient' => $normalizedRecipient,
                'subject' => $subject,
                'body' => $body,
                'result' => $result->toArray(),
                'event' => $event->toArray(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $metadata
     */
    private function recordEvent(
        string $tenantId,
        array $recipient,
        string $subject,
        array $metadata,
        EmailSendResultData $result,
    ): EmailEventData {
        return $this->emailEventRepository->record($tenantId, [
            'notification_id' => null,
            'source' => 'direct',
            'event_type' => $result->status === 'sent' ? 'sent' : 'failed',
            'recipient_email' => $result->recipientEmail,
            'recipient_name' => $result->recipientName,
            'subject' => $subject,
            'provider_key' => $result->providerKey,
            'message_id' => $result->messageId,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'metadata' => [
                ...$metadata,
                'recipient' => $recipient,
                'delivery' => $result->toArray(),
            ],
            'occurred_at' => $result->occurredAt,
        ]);
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

    /**
     * @param  array<string, mixed>  $recipient
     */
    private function recipientEmail(array $recipient): string
    {
        return $this->normalizedEmail($recipient['email'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $recipient
     */
    private function recipientName(array $recipient): ?string
    {
        return $this->normalizedNullableString($recipient['name'] ?? null);
    }

    private function normalizedEmail(mixed $value): string
    {
        return is_string($value) ? mb_strtolower(trim($value)) : '';
    }

    private function normalizedNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
