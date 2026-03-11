<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class DeleteClaimAttachmentCommand
{
    public function __construct(
        public string $claimId,
        public string $attachmentId,
    ) {}
}
