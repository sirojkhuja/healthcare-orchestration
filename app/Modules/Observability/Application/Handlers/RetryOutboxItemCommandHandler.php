<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\RetryOutboxItemCommand;
use App\Modules\Observability\Application\Services\OutboxAdministrationService;
use App\Shared\Application\Data\OutboxMessage;

final class RetryOutboxItemCommandHandler
{
    public function __construct(private readonly OutboxAdministrationService $outboxAdministrationService) {}

    public function handle(RetryOutboxItemCommand $command): OutboxMessage
    {
        return $this->outboxAdministrationService->retry($command->outboxId);
    }
}
