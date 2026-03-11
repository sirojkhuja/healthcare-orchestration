<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Data\RenderedNotificationTemplateData;

interface NotificationTemplateRenderer
{
    /**
     * @return list<string>
     */
    public function placeholders(?string $subjectTemplate, string $bodyTemplate): array;

    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(NotificationTemplateData $template, array $variables): RenderedNotificationTemplateData;
}
