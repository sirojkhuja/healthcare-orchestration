<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\TestRenderTemplateCommand;
use App\Modules\Notifications\Application\Data\RenderedNotificationTemplateData;
use App\Modules\Notifications\Application\Services\NotificationTemplateRenderService;

final readonly class TestRenderTemplateCommandHandler
{
    public function __construct(
        private NotificationTemplateRenderService $service,
    ) {}

    public function handle(TestRenderTemplateCommand $command): RenderedNotificationTemplateData
    {
        return $this->service->render($command->templateId, $command->variables);
    }
}
