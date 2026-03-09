<?php

namespace App\Modules\Provider\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderWorkHoursData
{
    /**
     * @param  array<string, list<array{start_time: string, end_time: string}>>  $days
     */
    public function __construct(
        public string $providerId,
        public array $days,
        public ?string $timezone = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'timezone' => $this->timezone,
            'days' => $this->days,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
