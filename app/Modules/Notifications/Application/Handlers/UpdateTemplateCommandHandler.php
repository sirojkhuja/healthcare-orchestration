<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\UpdateTemplateCommand;
use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Services\NotificationTemplateService;

final readonly class UpdateTemplateCommandHandler
{
    public function __construct(
        private NotificationTemplateService $service,
    ) {}

    public function handle(UpdateTemplateCommand $command): NotificationTemplateData
    {
        return $this->service->update($command->templateId, $command->attributes);
    }
}
