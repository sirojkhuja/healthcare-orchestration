<?php

namespace App\Modules\Insurance\Application\Commands;

use Illuminate\Http\UploadedFile;

final readonly class UploadClaimAttachmentCommand
{
    public function __construct(
        public string $claimId,
        public UploadedFile $file,
        public ?string $attachmentType = null,
        public ?string $notes = null,
    ) {}
}
