<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Data\UserProfileData;
use App\Modules\IdentityAccess\Application\Queries\GetProfileQuery;
use App\Modules\IdentityAccess\Application\Services\UserProfileService;

final class GetProfileQueryHandler
{
    public function __construct(
        private readonly UserProfileService $userProfileService,
    ) {}

    public function handle(GetProfileQuery $query): UserProfileData
    {
        return $this->userProfileService->profileForTenant($query->userId);
    }
}
