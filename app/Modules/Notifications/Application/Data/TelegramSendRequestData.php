<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class TelegramSendRequestData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $tenantId,
        public string $chatId,
        public string $message,
        public string $parseMode,
        public array $metadata = [],
        public ?string $notificationId = null,
    ) {}
}
