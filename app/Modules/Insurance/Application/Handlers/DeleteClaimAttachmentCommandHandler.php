<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\DeleteClaimAttachmentCommand;
use App\Modules\Insurance\Application\Data\ClaimAttachmentData;
use App\Modules\Insurance\Application\Services\ClaimAttachmentService;

final readonly class DeleteClaimAttachmentCommandHandler
{
    public function __construct(
        private ClaimAttachmentService $service,
    ) {}

    public function handle(DeleteClaimAttachmentCommand $command): ClaimAttachmentData
    {
        return $this->service->delete($command->claimId, $command->attachmentId);
    }
}
