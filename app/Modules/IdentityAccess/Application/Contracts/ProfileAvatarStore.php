<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\ProfileAvatarData;
use Illuminate\Http\UploadedFile;

interface ProfileAvatarStore
{
    public function storeForUser(string $userId, UploadedFile $file): ProfileAvatarData;

    public function delete(?ProfileAvatarData $avatar): void;
}
