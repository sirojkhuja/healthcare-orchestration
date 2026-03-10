<?php

namespace App\Modules\Scheduling\Domain\Appointments;

enum AppointmentStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
    case NO_SHOW = 'no_show';
    case RESCHEDULED = 'rescheduled';

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

    public function isRecoverableTerminal(): bool
    {
        return match ($this) {
            self::CANCELED, self::NO_SHOW, self::RESCHEDULED => true,
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::CANCELED, self::NO_SHOW, self::RESCHEDULED => true,
            default => false,
        };
    }
}
