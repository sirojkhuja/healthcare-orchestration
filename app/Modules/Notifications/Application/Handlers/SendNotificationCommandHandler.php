<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\SendNotificationCommand;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Services\NotificationDispatchService;

final readonly class SendNotificationCommandHandler
{
    public function __construct(
        private NotificationDispatchService $service,
    ) {}

    public function handle(SendNotificationCommand $command): NotificationData
    {
        return $this->service->queue($command->attributes);
    }
}
