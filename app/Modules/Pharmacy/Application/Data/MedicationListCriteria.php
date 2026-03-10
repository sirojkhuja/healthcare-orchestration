<?php

namespace App\Modules\Pharmacy\Application\Data;

final readonly class MedicationListCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?bool $isActive = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array{
     *     q: string|null,
     *     is_active: bool|null,
     *     limit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'is_active' => $this->isActive,
            'limit' => $this->limit,
        ];
    }
}
