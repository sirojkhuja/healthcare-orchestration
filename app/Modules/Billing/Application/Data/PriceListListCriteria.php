<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PriceListListCriteria
{
    public function __construct(
        public ?string $query,
        public ?bool $isActive,
        public ?bool $isDefault,
        public ?CarbonImmutable $activeOn,
        public int $limit = 25,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'is_active' => $this->isActive,
            'is_default' => $this->isDefault,
            'active_on' => $this->activeOn?->toDateString(),
            'limit' => $this->limit,
        ];
    }
}
