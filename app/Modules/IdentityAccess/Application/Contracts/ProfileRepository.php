<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\ProfileAvatarData;
use App\Modules\IdentityAccess\Application\Data\ProfilePatchData;
use App\Modules\IdentityAccess\Application\Data\UserProfileData;

interface ProfileRepository
{
    public function findById(string $userId): ?UserProfileData;

    public function update(string $userId, ProfilePatchData $patch): UserProfileData;

    public function updateAvatar(string $userId, ?ProfileAvatarData $avatar): UserProfileData;
}
