<?php

namespace App\Modules\Notifications\Application\Queries;

use App\Modules\Notifications\Application\Data\NotificationListCriteria;

final readonly class ListNotificationsQuery
{
    public function __construct(
        public NotificationListCriteria $criteria,
    ) {}
}
