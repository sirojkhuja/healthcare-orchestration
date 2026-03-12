<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Commands\ReEncryptPiiCommand;
use App\Modules\AuditCompliance\Application\Data\ComplianceReportData;
use App\Modules\AuditCompliance\Application\Services\PiiGovernanceService;

final class ReEncryptPiiCommandHandler
{
    public function __construct(
        private readonly PiiGovernanceService $piiGovernanceService,
    ) {}

    public function handle(ReEncryptPiiCommand $command): ComplianceReportData
    {
        return $this->piiGovernanceService->reEncrypt($command->fieldIds);
    }
}
