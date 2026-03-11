<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class TelegramBotProfileData
{
    public function __construct(
        public string $botId,
        public string $username,
        public ?string $displayName = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->botId,
            'username' => $this->username,
            'display_name' => $this->displayName,
        ];
    }
}
