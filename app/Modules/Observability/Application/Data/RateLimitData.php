<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class RateLimitData
{
    public function __construct(
        public string $bucketKey,
        public string $name,
        public string $description,
        public int $requestsPerMinute,
        public int $burst,
        public string $source,
        public ?CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bucket_key' => $this->bucketKey,
            'name' => $this->name,
            'description' => $this->description,
            'requests_per_minute' => $this->requestsPerMinute,
            'burst' => $this->burst,
            'source' => $this->source,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
