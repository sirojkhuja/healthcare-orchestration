<?php

namespace App\Modules\Lab\Application\Handlers;

use App\Modules\Lab\Application\Commands\BulkUpdateLabOrdersCommand;
use App\Modules\Lab\Application\Data\BulkLabOrderUpdateData;
use App\Modules\Lab\Application\Services\LabOrderBulkUpdateService;

final class BulkUpdateLabOrdersCommandHandler
{
    public function __construct(
        private readonly LabOrderBulkUpdateService $labOrderBulkUpdateService,
    ) {}

    public function handle(BulkUpdateLabOrdersCommand $command): BulkLabOrderUpdateData
    {
        return $this->labOrderBulkUpdateService->update($command->orderIds, $command->changes);
    }
}
