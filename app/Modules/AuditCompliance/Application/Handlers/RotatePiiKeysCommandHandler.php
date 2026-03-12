<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Commands\RotatePiiKeysCommand;
use App\Modules\AuditCompliance\Application\Data\ComplianceReportData;
use App\Modules\AuditCompliance\Application\Services\PiiGovernanceService;

final class RotatePiiKeysCommandHandler
{
    public function __construct(
        private readonly PiiGovernanceService $piiGovernanceService,
    ) {}

    public function handle(RotatePiiKeysCommand $command): ComplianceReportData
    {
        return $this->piiGovernanceService->rotateKeys($command->fieldIds);
    }
}
