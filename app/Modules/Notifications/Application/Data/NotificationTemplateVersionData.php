<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class NotificationTemplateVersionData
{
    /**
     * @param  list<string>  $placeholders
     */
    public function __construct(
        public int $version,
        public string $code,
        public string $name,
        public string $channel,
        public ?string $description,
        public bool $isActive,
        public ?string $subjectTemplate,
        public string $bodyTemplate,
        public array $placeholders,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'code' => $this->code,
            'name' => $this->name,
            'channel' => $this->channel,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'subject_template' => $this->subjectTemplate,
            'body_template' => $this->bodyTemplate,
            'placeholders' => $this->placeholders,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
