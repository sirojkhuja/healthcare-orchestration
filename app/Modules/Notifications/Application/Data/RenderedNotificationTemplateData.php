<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class RenderedNotificationTemplateData
{
    /**
     * @param  list<string>  $placeholders
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public string $templateId,
        public string $code,
        public string $channel,
        public int $currentVersion,
        public array $placeholders,
        public array $variables,
        public ?string $renderedSubject,
        public string $renderedBody,
        public CarbonImmutable $renderedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'template_id' => $this->templateId,
            'code' => $this->code,
            'channel' => $this->channel,
            'current_version' => $this->currentVersion,
            'placeholders' => $this->placeholders,
            'variables' => $this->variables,
            'rendered_subject' => $this->renderedSubject,
            'rendered_body' => $this->renderedBody,
            'rendered_at' => $this->renderedAt->toIso8601String(),
        ];
    }
}
