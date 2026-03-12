<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Commands\CreateDataAccessRequestCommand;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Services\DataAccessRequestService;

final class CreateDataAccessRequestCommandHandler
{
    public function __construct(
        private readonly DataAccessRequestService $dataAccessRequestService,
    ) {}

    public function handle(CreateDataAccessRequestCommand $command): DataAccessRequestData
    {
        return $this->dataAccessRequestService->create($command->attributes);
    }
}
