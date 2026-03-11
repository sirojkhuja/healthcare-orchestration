<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class NotificationListCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $channel = null,
        public ?string $templateId = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array{
     *     q: string|null,
     *     status: string|null,
     *     channel: string|null,
     *     template_id: string|null,
     *     created_from: string|null,
     *     created_to: string|null,
     *     limit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'status' => $this->status,
            'channel' => $this->channel,
            'template_id' => $this->templateId,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
