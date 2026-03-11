<?php

namespace App\Modules\Notifications\Domain;

enum NotificationTemplateChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case TELEGRAM = 'telegram';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (self $channel): string => $channel->value,
            self::cases(),
        );
    }
}
