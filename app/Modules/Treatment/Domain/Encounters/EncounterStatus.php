<?php

namespace App\Modules\Treatment\Domain\Encounters;

enum EncounterStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';
    case ENTERED_IN_ERROR = 'entered_in_error';

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
}
