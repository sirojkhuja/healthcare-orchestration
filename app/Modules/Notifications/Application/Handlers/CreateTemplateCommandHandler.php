<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\CreateTemplateCommand;
use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Services\NotificationTemplateService;

final readonly class CreateTemplateCommandHandler
{
    public function __construct(
        private NotificationTemplateService $service,
    ) {}

    public function handle(CreateTemplateCommand $command): NotificationTemplateData
    {
        return $this->service->create($command->attributes);
    }
}
