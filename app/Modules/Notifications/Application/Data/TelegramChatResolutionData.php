<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class TelegramChatResolutionData
{
    public function __construct(
        public string $tenantId,
        public string $chatId,
        public bool $isSupportChat,
        public bool $isBroadcastChat,
    ) {}
}
