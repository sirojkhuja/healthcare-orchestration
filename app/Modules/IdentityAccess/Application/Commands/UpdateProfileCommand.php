<?php

namespace App\Modules\IdentityAccess\Application\Commands;

use App\Modules\IdentityAccess\Application\Data\ProfilePatchData;

final readonly class UpdateProfileCommand
{
    public function __construct(
        public string $userId,
        public ProfilePatchData $patch,
    ) {}
}
