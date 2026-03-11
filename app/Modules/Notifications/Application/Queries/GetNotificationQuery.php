<?php

namespace App\Modules\Notifications\Application\Queries;

final readonly class GetNotificationQuery
{
    public function __construct(
        public string $notificationId,
    ) {}
}
