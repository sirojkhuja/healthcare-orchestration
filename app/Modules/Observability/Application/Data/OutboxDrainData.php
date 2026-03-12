<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class OutboxDrainData
{
    public function __construct(
        public int $limit,
        public int $claimed,
        public int $delivered,
        public int $failed,
        public CarbonImmutable $performedAt,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'limit' => $this->limit,
            'claimed' => $this->claimed,
            'delivered' => $this->delivered,
            'failed' => $this->failed,
            'performed_at' => $this->performedAt->toIso8601String(),
        ];
    }
}
