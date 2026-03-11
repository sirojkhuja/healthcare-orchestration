<?php

namespace App\Modules\Notifications\Domain;

enum NotificationStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELED = 'canceled';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::QUEUED, self::FAILED], true);
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }
}
