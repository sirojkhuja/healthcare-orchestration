<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class NotificationTemplateData
{
    /**
     * @param  list<string>  $placeholders
     */
    public function __construct(
        public string $templateId,
        public string $tenantId,
        public string $code,
        public string $name,
        public string $channel,
        public ?string $description,
        public bool $isActive,
        public int $currentVersion,
        public ?string $subjectTemplate,
        public string $bodyTemplate,
        public array $placeholders,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public ?CarbonImmutable $deletedAt = null,
    ) {}

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     channel: string,
     *     description: string|null,
     *     is_active: bool,
     *     subject_template: string|null,
     *     body_template: string,
     *     placeholders: list<string>
     * }
     */
    public function currentSnapshot(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'channel' => $this->channel,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'subject_template' => $this->subjectTemplate,
            'body_template' => $this->bodyTemplate,
            'placeholders' => $this->placeholders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->templateId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'channel' => $this->channel,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'current_version' => $this->currentVersion,
            'subject_template' => $this->subjectTemplate,
            'body_template' => $this->bodyTemplate,
            'placeholders' => $this->placeholders,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'deleted_at' => $this->deletedAt?->toIso8601String(),
        ];
    }
}
