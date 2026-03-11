<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Data\SmsDeliveryRequestData;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class SmsDiagnosticSendService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationRecipientNormalizer $notificationRecipientNormalizer,
        private readonly SmsMessageTypeResolver $smsMessageTypeResolver,
        private readonly SmsRoutingService $smsRoutingService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function send(array $attributes, ?string $providerKey = null): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $recipient = $this->requiredArray($attributes, 'recipient');
        $metadata = $this->optionalArray($attributes, 'metadata');
        $normalizedRecipient = $this->notificationRecipientNormalizer->normalize(
            NotificationTemplateChannel::SMS->value,
            $recipient,
        );
        $message = $this->requiredString($attributes, 'message');
        $messageType = $this->messageType($attributes['message_type'] ?? null, $metadata);
        $phoneNumber = $normalizedRecipient['recipient']['phone_number'] ?? null;

        if (! is_string($phoneNumber) || trim($phoneNumber) === '') {
            throw new UnprocessableEntityHttpException('SMS recipients require a valid recipient.phone_number value.');
        }

        $result = $this->smsRoutingService->send(
            new SmsDeliveryRequestData(
                tenantId: $tenantId,
                phoneNumber: trim($phoneNumber),
                message: $message,
                messageType: $messageType,
                metadata: $metadata,
            ),
            providerOrder: $providerKey === null ? null : [$providerKey],
            attemptBudget: $providerKey === null ? null : 1,
        );

        return [
            'channel' => 'sms',
            'recipient' => $normalizedRecipient['recipient'],
            'message' => $message,
            'message_type' => $messageType,
            'result' => $result->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function messageType(mixed $value, array $metadata): string
    {
        if ($value !== null && ! is_string($value)) {
            throw new UnprocessableEntityHttpException('The message_type field must be a string.');
        }

        $candidate = is_string($value) ? strtolower(trim($value)) : null;

        return $this->smsMessageTypeResolver->resolve(
            $candidate === null || $candidate === '' ? $metadata : [...$metadata, 'message_type' => $candidate],
        );
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
