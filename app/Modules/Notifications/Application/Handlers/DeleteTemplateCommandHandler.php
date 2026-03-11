<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\DeleteTemplateCommand;
use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Services\NotificationTemplateService;

final readonly class DeleteTemplateCommandHandler
{
    public function __construct(
        private NotificationTemplateService $service,
    ) {}

    public function handle(DeleteTemplateCommand $command): NotificationTemplateData
    {
        return $this->service->delete($command->templateId);
    }
}
