<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Commands\DenyDataAccessRequestCommand;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Services\DataAccessRequestService;

final class DenyDataAccessRequestCommandHandler
{
    public function __construct(
        private readonly DataAccessRequestService $dataAccessRequestService,
    ) {}

    public function handle(DenyDataAccessRequestCommand $command): DataAccessRequestData
    {
        return $this->dataAccessRequestService->deny(
            $command->requestId,
            $command->reason,
            $command->decisionNotes,
        );
    }
}
