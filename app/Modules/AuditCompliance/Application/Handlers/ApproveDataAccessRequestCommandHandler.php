<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Commands\ApproveDataAccessRequestCommand;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Services\DataAccessRequestService;

final class ApproveDataAccessRequestCommandHandler
{
    public function __construct(
        private readonly DataAccessRequestService $dataAccessRequestService,
    ) {}

    public function handle(ApproveDataAccessRequestCommand $command): DataAccessRequestData
    {
        return $this->dataAccessRequestService->approve($command->requestId, $command->decisionNotes);
    }
}
