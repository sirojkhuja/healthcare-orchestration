<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\TelegramBotProfileData;
use App\Modules\Notifications\Application\Data\TelegramSendRequestData;
use App\Modules\Notifications\Application\Data\TelegramSendResultData;
use App\Modules\Notifications\Application\Data\TelegramWebhookInfoData;

interface TelegramBotGateway
{
    public function providerKey(): string;

    public function verifyWebhookSecret(string $secretToken): bool;

    public function sendMessage(TelegramSendRequestData $request): TelegramSendResultData;

    public function getMe(): TelegramBotProfileData;

    public function getWebhookInfo(): TelegramWebhookInfoData;

    public function setWebhook(string $url, string $secretToken): TelegramWebhookInfoData;
}
