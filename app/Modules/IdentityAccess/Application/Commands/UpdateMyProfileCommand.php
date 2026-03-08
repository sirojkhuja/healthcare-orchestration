<?php

namespace App\Modules\IdentityAccess\Application\Commands;

use App\Modules\IdentityAccess\Application\Data\ProfilePatchData;

final readonly class UpdateMyProfileCommand
{
    public function __construct(
        public ProfilePatchData $patch,
    ) {}
}
