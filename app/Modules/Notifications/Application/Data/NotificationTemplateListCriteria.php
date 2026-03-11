<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class NotificationTemplateListCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $channel = null,
        public ?bool $isActive = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'channel' => $this->channel,
            'is_active' => $this->isActive,
            'limit' => $this->limit,
        ];
    }
}
