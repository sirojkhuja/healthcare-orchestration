<?php

namespace App\Shared\Application\Data;

final readonly class GlobalSearchItemData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $title,
        public ?string $subtitle,
        public ?string $status,
        public int $score,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'status' => $this->status,
            'score' => $this->score,
            'metadata' => $this->metadata,
        ];
    }
}
