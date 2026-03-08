<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization;

use App\Modules\IdentityAccess\Application\Contracts\PermissionAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Data\PermissionProjection;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

final class CachedPermissionAuthorizer implements PermissionAuthorizer
{
    public function __construct(
        private readonly PermissionProjectionRepository $permissionProjectionRepository,
        private readonly CacheFactory $cacheFactory,
    ) {}

    #[\Override]
    public function allows(string $userId, ?string $tenantId, string $permission): bool
    {
        return $this->projection($userId, $tenantId)->allows($permission);
    }

    #[\Override]
    public function forget(string $userId, ?string $tenantId): void
    {
        $this->cacheFactory->store()->forget($this->cacheKey($userId, $tenantId));
    }

    private function projection(string $userId, ?string $tenantId): PermissionProjection
    {
        /** @var PermissionProjection $projection */
        $projection = $this->cacheFactory->store()->rememberForever(
            $this->cacheKey($userId, $tenantId),
            fn () => $this->permissionProjectionRepository->forUser($userId, $tenantId),
        );

        return $projection;
    }

    private function cacheKey(string $userId, ?string $tenantId): string
    {
        $tenantKey = $tenantId ?? 'global';

        return "permissions:{$tenantKey}:{$userId}";
    }
}
