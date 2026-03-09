<?php

namespace App\Modules\Scheduling\Application\Data;

final readonly class ProviderCalendarWindowData
{
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public int $limit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'limit' => $this->limit,
        ];
    }
}
