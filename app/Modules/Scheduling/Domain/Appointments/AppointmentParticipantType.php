<?php

namespace App\Modules\Scheduling\Domain\Appointments;

enum AppointmentParticipantType: string
{
    case USER = 'user';
    case PROVIDER = 'provider';
    case EXTERNAL = 'external';

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
