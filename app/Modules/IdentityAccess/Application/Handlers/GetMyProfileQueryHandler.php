<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Data\UserProfileData;
use App\Modules\IdentityAccess\Application\Queries\GetMyProfileQuery;
use App\Modules\IdentityAccess\Application\Services\UserProfileService;

final class GetMyProfileQueryHandler
{
    public function __construct(
        private readonly UserProfileService $userProfileService,
    ) {}

    public function handle(GetMyProfileQuery $query): UserProfileData
    {
        return $this->userProfileService->myProfile();
    }
}
