<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\RemoveFromWaitlistCommand;
use App\Modules\Scheduling\Application\Data\WaitlistEntryData;
use App\Modules\Scheduling\Application\Services\WaitlistService;

final class RemoveFromWaitlistCommandHandler
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
    ) {}

    public function handle(RemoveFromWaitlistCommand $command): WaitlistEntryData
    {
        return $this->waitlistService->remove($command->entryId);
    }
}
