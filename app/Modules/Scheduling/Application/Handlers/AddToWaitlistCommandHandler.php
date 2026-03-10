<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\AddToWaitlistCommand;
use App\Modules\Scheduling\Application\Data\WaitlistEntryData;
use App\Modules\Scheduling\Application\Services\WaitlistService;

final class AddToWaitlistCommandHandler
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
    ) {}

    public function handle(AddToWaitlistCommand $command): WaitlistEntryData
    {
        return $this->waitlistService->create($command->attributes);
    }
}
