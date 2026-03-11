<?php

namespace App\Modules\Notifications\Application\Commands;

final readonly class RetryNotificationCommand
{
    public function __construct(
        public string $notificationId,
    ) {}
}
