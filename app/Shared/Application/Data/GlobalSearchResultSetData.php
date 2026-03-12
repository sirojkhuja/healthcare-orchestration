<?php

namespace App\Shared\Application\Data;

final readonly class GlobalSearchResultSetData
{
    /**
     * @param  array<string, list<GlobalSearchItemData>>  $results
     * @param  list<string>  $requestedTypes
     * @param  list<string>  $returnedTypes
     */
    public function __construct(
        public GlobalSearchCriteria $criteria,
        public array $results,
        public array $requestedTypes,
        public array $returnedTypes,
    ) {}

    public function totalResults(): int
    {
        $total = 0;

        foreach ($this->results as $items) {
            $total += count($items);
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->results as $type => $items) {
            $data[$type] = array_map(
                static fn (GlobalSearchItemData $item): array => $item->toArray(),
                $items,
            );
        }

        return [
            'data' => $data,
            'meta' => [
                'filters' => $this->criteria->toArray(),
                'requested_types' => $this->requestedTypes,
                'returned_types' => $this->returnedTypes,
                'total_results' => $this->totalResults(),
            ],
        ];
    }
}
