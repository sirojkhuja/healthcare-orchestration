<?php

namespace App\Modules\IdentityAccess\Application\Commands;

use Illuminate\Http\UploadedFile;

final readonly class UploadMyAvatarCommand
{
    public function __construct(
        public UploadedFile $avatar,
    ) {}
}
