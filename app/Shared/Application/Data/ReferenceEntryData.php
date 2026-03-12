<?php

namespace App\Shared\Application\Data;

final readonly class ReferenceEntryData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $code,
        public string $name,
        public bool $isActive,
        public array $metadata = [],
    ) {}

    /**
     * @return array{code: string, name: string, is_active: bool, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
        ];
    }
}
