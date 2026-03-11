<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class NotificationTemplateDetailsData
{
    /**
     * @param  list<NotificationTemplateVersionData>  $versions
     */
    public function __construct(
        public NotificationTemplateData $template,
        public array $versions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->template->toArray(),
            'versions' => array_map(
                static fn (NotificationTemplateVersionData $version): array => $version->toArray(),
                $this->versions,
            ),
        ];
    }
}
