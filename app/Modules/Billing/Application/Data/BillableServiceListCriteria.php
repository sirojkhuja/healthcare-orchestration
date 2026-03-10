<?php

namespace App\Modules\Billing\Application\Data;

final readonly class BillableServiceListCriteria
{
    public function __construct(
        public ?string $query,
        public ?string $category,
        public ?bool $isActive,
        public int $limit = 25,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'category' => $this->category,
            'is_active' => $this->isActive,
            'limit' => $this->limit,
        ];
    }
}
