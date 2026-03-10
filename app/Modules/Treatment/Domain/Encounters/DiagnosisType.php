<?php

namespace App\Modules\Treatment\Domain\Encounters;

enum DiagnosisType: string
{
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';

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
