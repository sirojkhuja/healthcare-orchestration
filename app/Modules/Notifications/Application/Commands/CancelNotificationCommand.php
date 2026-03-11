<?php

namespace App\Modules\Notifications\Application\Commands;

final readonly class CancelNotificationCommand
{
    public function __construct(
        public string $notificationId,
        public ?string $reason = null,
    ) {}
}
