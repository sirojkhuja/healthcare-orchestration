<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class EmailEventListCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $source = null,
        public ?string $eventType = null,
        public ?string $notificationId = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'source' => $this->source,
            'event_type' => $this->eventType,
            'notification_id' => $this->notificationId,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
