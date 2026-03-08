<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\UpdateProfileCommand;
use App\Modules\IdentityAccess\Application\Data\UserProfileData;
use App\Modules\IdentityAccess\Application\Services\UserProfileService;

final class UpdateProfileCommandHandler
{
    public function __construct(
        private readonly UserProfileService $userProfileService,
    ) {}

    public function handle(UpdateProfileCommand $command): UserProfileData
    {
        return $this->userProfileService->updateProfileForTenant($command->userId, $command->patch);
    }
}
