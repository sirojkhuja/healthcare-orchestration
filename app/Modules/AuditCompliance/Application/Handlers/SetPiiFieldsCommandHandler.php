<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Commands\SetPiiFieldsCommand;
use App\Modules\AuditCompliance\Application\Data\PiiFieldData;
use App\Modules\AuditCompliance\Application\Services\PiiGovernanceService;

final class SetPiiFieldsCommandHandler
{
    public function __construct(
        private readonly PiiGovernanceService $piiGovernanceService,
    ) {}

    /**
     * @return list<PiiFieldData>
     */
    public function handle(SetPiiFieldsCommand $command): array
    {
        return $this->piiGovernanceService->replaceFields($command->fields);
    }
}
