<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\RetryNotificationCommand;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Services\NotificationDispatchService;

final readonly class RetryNotificationCommandHandler
{
    public function __construct(
        private NotificationDispatchService $service,
    ) {}

    public function handle(RetryNotificationCommand $command): NotificationData
    {
        return $this->service->retry($command->notificationId);
    }
}
