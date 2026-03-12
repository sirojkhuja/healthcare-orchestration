<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\EmailGateway;
use App\Modules\Notifications\Application\Contracts\EmailProviderSettingsRepository;
use App\Modules\Notifications\Application\Data\EmailProviderSettingsData;
use App\Modules\Notifications\Application\Data\EmailSendRequestData;
use App\Modules\Notifications\Application\Data\EmailSendResultData;
use App\Modules\Notifications\Application\Exceptions\EmailGatewayException;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use App\Shared\Application\Contracts\ObservabilityMetricRecorder;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EmailGatewaySendService
{
    public function __construct(
        private readonly NotificationRecipientNormalizer $notificationRecipientNormalizer,
        private readonly EmailProviderSettingsRepository $emailProviderSettingsRepository,
        private readonly EmailGateway $emailGateway,
        private readonly ObservabilityMetricRecorder $metricRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $metadata
     * @return array{recipient: array<string, mixed>, settings: EmailProviderSettingsData, result: EmailSendResultData}
     */
    public function send(
        string $tenantId,
        array $recipient,
        string $subject,
        string $body,
        array $metadata = [],
        ?string $notificationId = null,
    ): array {
        $settings = $this->emailProviderSettingsRepository->get($tenantId);

        if (! $settings->enabled) {
            throw new EmailGatewayException('email_disabled', 'The email provider is disabled for the current tenant.');
        }

        $normalizedRecipient = $this->notificationRecipientNormalizer->normalize(
            NotificationTemplateChannel::EMAIL->value,
            $recipient,
        );
        /** @var array<string, mixed> $recipientPayload */
        $recipientPayload = $normalizedRecipient['recipient'];
        $email = $this->requiredRecipientString($recipientPayload['email'] ?? null);
        $name = $this->nullableRecipientString($recipientPayload['name'] ?? null);

        try {
            $result = $this->emailGateway->send(new EmailSendRequestData(
                tenantId: $tenantId,
                recipientEmail: $email,
                recipientName: $name,
                subject: trim($subject),
                body: $body,
                metadata: $metadata,
                fromAddress: $settings->fromAddress,
                fromName: $settings->fromName,
                replyToAddress: $settings->replyToAddress,
                replyToName: $settings->replyToName,
                notificationId: $notificationId,
            ));
        } catch (Throwable $exception) {
            $this->metricRecorder->recordIntegrationError($settings->providerKey, 'email.send', $exception::class);
            Log::warning('integration.email.failed', [
                'provider_key' => $settings->providerKey,
                'operation' => 'email.send',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return [
            'recipient' => $recipientPayload,
            'settings' => $settings,
            'result' => $result,
        ];
    }

    private function requiredRecipientString(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function nullableRecipientString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
