<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Commands\CancelNotificationCommand;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Services\NotificationDispatchService;

final readonly class CancelNotificationCommandHandler
{
    public function __construct(
        private NotificationDispatchService $service,
    ) {}

    public function handle(CancelNotificationCommand $command): NotificationData
    {
        return $this->service->cancel($command->notificationId, $command->reason);
    }
}
