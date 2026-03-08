<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TenantSettingsData
{
    public function __construct(
        public ?string $locale = null,
        public ?string $timezone = null,
        public ?string $currency = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * @return array{
     *     locale: string|null,
     *     timezone: string|null,
     *     currency: string|null,
     *     updated_at: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
