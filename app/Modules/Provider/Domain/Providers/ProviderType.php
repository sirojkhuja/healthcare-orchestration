<?php

namespace App\Modules\Provider\Domain\Providers;

enum ProviderType: string
{
    case DOCTOR = 'doctor';
    case NURSE = 'nurse';
    case OTHER = 'other';

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
