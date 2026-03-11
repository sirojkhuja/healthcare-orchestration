<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Queries\ListNotificationsQuery;
use App\Modules\Notifications\Application\Services\NotificationReadService;

final readonly class ListNotificationsQueryHandler
{
    public function __construct(
        private NotificationReadService $service,
    ) {}

    /**
     * @return list<NotificationData>
     */
    public function handle(ListNotificationsQuery $query): array
    {
        return $this->service->list($query->criteria);
    }
}
