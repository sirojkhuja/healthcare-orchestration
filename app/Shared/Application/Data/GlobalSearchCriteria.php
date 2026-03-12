<?php

namespace App\Shared\Application\Data;

final readonly class GlobalSearchCriteria
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public string $query,
        public array $types = [],
        public int $limitPerType = 5,
    ) {}

    public function normalizedQuery(): string
    {
        return mb_strtolower(trim($this->query));
    }

    /**
     * @return list<string>
     */
    public function normalizedTypes(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $type): string => mb_strtolower(trim($type)),
            $this->types,
        )));
    }

    /**
     * @return array{q: string, types: list<string>, limit_per_type: int}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'types' => $this->normalizedTypes(),
            'limit_per_type' => $this->limitPerType,
        ];
    }
}
