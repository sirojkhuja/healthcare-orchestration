<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Commands\UpdateAuditRetentionCommand;
use App\Modules\AuditCompliance\Application\Data\AuditRetentionData;
use App\Modules\AuditCompliance\Application\Services\AuditRetentionService;

final class UpdateAuditRetentionCommandHandler
{
    public function __construct(
        private readonly AuditRetentionService $auditRetentionService,
    ) {}

    public function handle(UpdateAuditRetentionCommand $command): AuditRetentionData
    {
        return $this->auditRetentionService->update($command->retentionDays);
    }
}
