<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Data\EmailSendResultData;
use App\Modules\Notifications\Application\Exceptions\EmailGatewayException;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

final class EmailDiagnosticSendService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EmailGatewaySendService $emailGatewaySendService,
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
            if ($exception instanceof EmailGatewayException && $exception->errorCode() === 'email_disabled') {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

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

        return [
            'channel' => 'email',
            'recipient' => $normalizedRecipient,
            'subject' => $subject,
            'body' => $body,
            'result' => $result->toArray(),
        ];
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
