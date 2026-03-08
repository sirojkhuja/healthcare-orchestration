<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Commands\UploadMyAvatarCommand;
use App\Modules\IdentityAccess\Application\Data\UserProfileData;
use App\Modules\IdentityAccess\Application\Services\UserProfileService;

final class UploadMyAvatarCommandHandler
{
    public function __construct(
        private readonly UserProfileService $userProfileService,
    ) {}

    public function handle(UploadMyAvatarCommand $command): UserProfileData
    {
        return $this->userProfileService->uploadMyAvatar($command->avatar);
    }
}
