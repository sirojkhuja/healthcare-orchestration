<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\UploadClaimAttachmentCommand;
use App\Modules\Insurance\Application\Data\ClaimAttachmentData;
use App\Modules\Insurance\Application\Services\ClaimAttachmentService;

final readonly class UploadClaimAttachmentCommandHandler
{
    public function __construct(
        private ClaimAttachmentService $service,
    ) {}

    public function handle(UploadClaimAttachmentCommand $command): ClaimAttachmentData
    {
        return $this->service->upload(
            claimId: $command->claimId,
            file: $command->file,
            attachmentType: $command->attachmentType,
            notes: $command->notes,
        );
    }
}
