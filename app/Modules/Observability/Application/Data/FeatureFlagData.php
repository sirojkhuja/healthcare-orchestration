<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class FeatureFlagData
{
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public string $module,
        public bool $enabled,
        public bool $defaultEnabled,
        public string $source,
        public ?CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'module' => $this->module,
            'enabled' => $this->enabled,
            'default_enabled' => $this->defaultEnabled,
            'source' => $this->source,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
