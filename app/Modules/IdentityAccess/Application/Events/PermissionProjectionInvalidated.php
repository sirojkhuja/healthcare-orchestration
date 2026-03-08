<?php

namespace App\Modules\IdentityAccess\Application\Events;

final readonly class PermissionProjectionInvalidated
{
    public function __construct(
        public string $userId,
        public ?string $tenantId,
    ) {}
}
