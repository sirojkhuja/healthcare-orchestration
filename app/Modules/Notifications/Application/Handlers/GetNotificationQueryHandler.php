<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Queries\GetNotificationQuery;
use App\Modules\Notifications\Application\Services\NotificationReadService;

final readonly class GetNotificationQueryHandler
{
    public function __construct(
        private NotificationReadService $service,
    ) {}

    public function handle(GetNotificationQuery $query): NotificationData
    {
        return $this->service->get($query->notificationId);
    }
}
