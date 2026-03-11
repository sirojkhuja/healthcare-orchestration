<?php

namespace App\Modules\Insurance\Domain\Claims;

enum ClaimStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case DENIED = 'denied';
    case PAID = 'paid';

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
