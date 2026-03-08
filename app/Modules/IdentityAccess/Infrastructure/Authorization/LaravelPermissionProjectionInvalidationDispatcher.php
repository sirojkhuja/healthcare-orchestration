<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization;

use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionInvalidationDispatcher;
use App\Modules\IdentityAccess\Application\Events\PermissionProjectionInvalidated;
use Illuminate\Contracts\Events\Dispatcher;

final class LaravelPermissionProjectionInvalidationDispatcher implements PermissionProjectionInvalidationDispatcher
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {}

    #[\Override]
    public function invalidate(string $userId, ?string $tenantId): void
    {
        $this->dispatcher->dispatch(new PermissionProjectionInvalidated($userId, $tenantId));
    }

    #[\Override]
    public function invalidateMany(array $userIds, ?string $tenantId): void
    {
        foreach (array_values(array_unique($userIds)) as $userId) {
            $this->invalidate($userId, $tenantId);
        }
    }
}
