<?php

namespace App\Modules\Reporting\Application\Data;

final readonly class ReportSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $source = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array{q: string|null, source: string|null, limit: int}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'source' => $this->source,
            'limit' => $this->limit,
        ];
    }
}
