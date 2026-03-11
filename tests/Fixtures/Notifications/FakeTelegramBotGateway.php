<?php

namespace Tests\Fixtures\Notifications;

use App\Modules\Notifications\Application\Contracts\TelegramBotGateway;
use App\Modules\Notifications\Application\Data\TelegramBotProfileData;
use App\Modules\Notifications\Application\Data\TelegramSendRequestData;
use App\Modules\Notifications\Application\Data\TelegramSendResultData;
use App\Modules\Notifications\Application\Data\TelegramWebhookInfoData;
use App\Modules\Notifications\Application\Exceptions\TelegramGatewayException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class FakeTelegramBotGateway implements TelegramBotGateway
{
    /**
     * @var list<array{type: string, message_id?: string, error_code?: string, error_message?: string}>
     */
    private array $plannedResults = [];

    /**
     * @var list<TelegramSendRequestData>
     */
    private array $sendRequests = [];

    /**
     * @var list<array{url: string, secret_token: string}>
     */
    private array $setWebhookCalls = [];

    public function __construct(
        private string $secretToken = 'telegram-secret',
        private ?TelegramBotProfileData $botProfile = null,
        private ?TelegramWebhookInfoData $webhookInfo = null,
    ) {
        $this->botProfile ??= new TelegramBotProfileData(
            botId: '777000',
            username: 'medflow_bot',
            displayName: 'MedFlow Bot',
        );
        $this->webhookInfo ??= new TelegramWebhookInfoData(
            url: 'https://stale.example/webhooks/telegram',
            hasCustomCertificate: false,
            pendingUpdateCount: 0,
        );
    }

    public function queueFailure(
        string $errorCode = 'telegram_rejected',
        string $errorMessage = 'Telegram rejected the request.',
    ): void {
        $this->plannedResults[] = [
            'type' => 'failure',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];
    }

    public function queueSuccess(?string $messageId = null): void
    {
        $this->plannedResults[] = [
            'type' => 'success',
            'message_id' => $messageId ?? 'telegram-'.Str::uuid()->toString(),
        ];
    }

    /**
     * @return list<TelegramSendRequestData>
     */
    public function sendRequests(): array
    {
        return $this->sendRequests;
    }

    /**
     * @return list<array{url: string, secret_token: string}>
     */
    public function setWebhookCalls(): array
    {
        return $this->setWebhookCalls;
    }

    #[\Override]
    public function getMe(): TelegramBotProfileData
    {
        return $this->botProfile;
    }

    #[\Override]
    public function getWebhookInfo(): TelegramWebhookInfoData
    {
        return $this->webhookInfo;
    }

    #[\Override]
    public function providerKey(): string
    {
        return 'telegram';
    }

    #[\Override]
    public function sendMessage(TelegramSendRequestData $request): TelegramSendResultData
    {
        $this->sendRequests[] = $request;
        $planned = array_shift($this->plannedResults);

        if (($planned['type'] ?? null) === 'failure') {
            throw new TelegramGatewayException(
                $planned['error_code'] ?? 'telegram_rejected',
                $planned['error_message'] ?? 'Telegram rejected the request.',
            );
        }

        return new TelegramSendResultData(
            providerKey: 'telegram',
            chatId: $request->chatId,
            status: 'sent',
            occurredAt: CarbonImmutable::now(),
            messageId: $planned['message_id'] ?? 'telegram-'.Str::uuid()->toString(),
        );
    }

    #[\Override]
    public function setWebhook(string $url, string $secretToken): TelegramWebhookInfoData
    {
        $this->setWebhookCalls[] = [
            'url' => $url,
            'secret_token' => $secretToken,
        ];
        $this->webhookInfo = new TelegramWebhookInfoData(
            url: $url,
            hasCustomCertificate: false,
            pendingUpdateCount: 0,
        );

        return $this->webhookInfo;
    }

    #[\Override]
    public function verifyWebhookSecret(string $secretToken): bool
    {
        return hash_equals($this->secretToken, trim($secretToken));
    }
}
