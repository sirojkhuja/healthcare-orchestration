<?php

namespace App\Modules\Notifications\Domain;

enum SmsMessageType: string
{
    case OTP = 'otp';
    case REMINDER = 'reminder';
    case TRANSACTIONAL = 'transactional';
    case BULK = 'bulk';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
