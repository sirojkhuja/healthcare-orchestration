<?php

namespace App\Modules\Lab\Application\Data;

final readonly class LabTestListCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $labProviderKey = null,
        public ?bool $isActive = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array{
     *     q: string|null,
     *     lab_provider_key: string|null,
     *     is_active: bool|null,
     *     limit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'lab_provider_key' => $this->labProviderKey,
            'is_active' => $this->isActive,
            'limit' => $this->limit,
        ];
    }
}
