<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\DrainOutboxCommand;
use App\Modules\Observability\Application\Data\OutboxDrainData;
use App\Modules\Observability\Application\Services\OutboxAdministrationService;

final class DrainOutboxCommandHandler
{
    public function __construct(private readonly OutboxAdministrationService $outboxAdministrationService) {}

    public function handle(DrainOutboxCommand $command): OutboxDrainData
    {
        return $this->outboxAdministrationService->drain($command->limit);
    }
}
