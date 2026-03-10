<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\OfferWaitlistSlotCommand;
use App\Modules\Scheduling\Application\Data\WaitlistOfferData;
use App\Modules\Scheduling\Application\Services\WaitlistService;

final class OfferWaitlistSlotCommandHandler
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
    ) {}

    public function handle(OfferWaitlistSlotCommand $command): WaitlistOfferData
    {
        return $this->waitlistService->offer($command->entryId, $command->attributes);
    }
}
