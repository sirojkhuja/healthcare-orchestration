<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Data\ClaimAttachmentData;
use App\Modules\Insurance\Application\Queries\ListClaimAttachmentsQuery;
use App\Modules\Insurance\Application\Services\ClaimAttachmentService;

final readonly class ListClaimAttachmentsQueryHandler
{
    public function __construct(
        private ClaimAttachmentService $service,
    ) {}

    /**
     * @return list<ClaimAttachmentData>
     */
    public function handle(ListClaimAttachmentsQuery $query): array
    {
        return $this->service->list($query->claimId);
    }
}
