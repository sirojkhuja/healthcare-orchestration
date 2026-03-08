<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\UpdateMyProfileCommand;
use App\Modules\IdentityAccess\Application\Data\UserProfileData;
use App\Modules\IdentityAccess\Application\Services\UserProfileService;

final class UpdateMyProfileCommandHandler
{
    public function __construct(
        private readonly UserProfileService $userProfileService,
    ) {}

    public function handle(UpdateMyProfileCommand $command): UserProfileData
    {
        return $this->userProfileService->updateMyProfile($command->patch);
    }
}
